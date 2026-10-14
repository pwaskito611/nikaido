<?php

namespace App\Platform\Strategy\Actions;

use App\Features\Engine\Models\MarketPrice;
use App\Platform\Strategy\Enums\StrategySignal;
use App\Platform\Strategy\Models\StrategyDecision;
use App\Platform\Strategy\Models\StrategyExecutionContext;
use Exact\Collection\Vec;
use Gauss\Number\Number;
use Gauss\TimeSeries\Smoothing\MovingAverage;
use Gauss\TimeSeries\TimeSeries;
use Generator;
use UnexpectedValueException;

/**
 * Example dummy strategy implementation; replace it with your own strategy logic.
 */
class GenerateMovingAverageCrossDecisionsAction
{
    private const FAST_EMA_PERIOD = 20;

    private const SLOW_EMA_PERIOD = 50;

    private const TREND_MA_PERIOD = 200;

    private const TAKE_PROFIT_RISK_MULTIPLE = 2.0;

    /**
     * @param  iterable<MarketPrice>  $candles  Must be in chronological order and include warm-up candles.
     * @return Generator<int, StrategyDecision>
     */
    public function execute(iterable $candles, StrategyExecutionContext $context): Generator
    {
        $candleList = Vec::empty();
        $closes = Vec::empty();

        foreach ($candles as $candle) {
            if (! is_numeric($candle->close)) {
                throw new UnexpectedValueException('Every candle must have a numeric close for MA cross analysis.');
            }

            $candleList = $candleList->append($candle);
            $closes = $closes->append((float) $candle->close);
        }

        if ($closes->count() < self::TREND_MA_PERIOD) {
            foreach ($candleList as $index => $_candle) {
                yield $index => new StrategyDecision(StrategySignal::None, null, null);
            }

            return;
        }

        $fastEma = $this->exponentialMovingAverage($closes, self::FAST_EMA_PERIOD);
        $slowEma = $this->exponentialMovingAverage($closes, self::SLOW_EMA_PERIOD);
        $trendMa = MovingAverage::smooth(TimeSeries::of($closes->toArray()), self::TREND_MA_PERIOD, false);
        foreach ($candleList as $index => $candle) {
            if ($index < self::TREND_MA_PERIOD - 1) {
                yield $index => new StrategyDecision(StrategySignal::None, null, null);

                continue;
            }

            $crossedUp = $fastEma->at($index - 1) <= $slowEma->at($index - 1)
                && $fastEma->at($index) > $slowEma->at($index);
            $crossedDown = $fastEma->at($index - 1) >= $slowEma->at($index - 1)
                && $fastEma->at($index) < $slowEma->at($index);
            $trendAverage = (float) $trendMa->valueAt($index)->value();
            $close = $closes->at($index);

            $decision = $crossedUp && $close > $trendAverage
                ? $this->entryDecision($candle, StrategySignal::Long, $context)
                : ($crossedDown && $close < $trendAverage
                    ? $this->entryDecision($candle, StrategySignal::Short, $context)
                    : new StrategyDecision(StrategySignal::None, null, null));

            yield $index => $decision;
        }
    }

    /**
     * @param  Vec<float>  $prices
     * @return Vec<float>
     */
    private function exponentialMovingAverage(Vec $prices, int $period): Vec
    {
        $alpha = 2 / ($period + 1);
        $ema = Vec::empty();
        $previous = null;

        foreach ($prices as $index => $price) {
            $previous = $previous === null
                ? $price
                : $alpha * $price + (1 - $alpha) * $previous;
            $ema = $ema->append($previous);
        }

        return $ema;
    }

    private function entryDecision(
        MarketPrice $candle,
        StrategySignal $signal,
        StrategyExecutionContext $context,
    ): StrategyDecision {
        $quoteStopLoss = $signal === StrategySignal::Long ? $candle->bid_low : $candle->ask_high;
        $fallbackStopLoss = $signal === StrategySignal::Long ? $candle->low : $candle->high;
        $stopLoss = is_numeric($quoteStopLoss) ? $quoteStopLoss : $fallbackStopLoss;
        $entryQuote = $signal === StrategySignal::Long ? $candle->ask_open : $candle->bid_open;
        $entry = is_numeric($entryQuote) ? $entryQuote : $candle->open;

        if (! is_numeric($stopLoss) || ! is_numeric($entry)
            || ! is_finite((float) $stopLoss) || ! is_finite((float) $entry)) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        $entryPrice = Number::of((string) $entry);
        $stopLossPrice = Number::of((string) $stopLoss);
        $isLong = $signal === StrategySignal::Long;
        $riskDistance = $entryPrice->sub($stopLossPrice)->abs();

        if ($riskDistance->compare(0) <= 0
            || ($isLong && $stopLossPrice->compare($entryPrice) >= 0)
            || (! $isLong && $stopLossPrice->compare($entryPrice) <= 0)) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        if (! is_finite($context->currentCapital) || $context->currentCapital <= 0) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        $riskMultiplier = Number::of((string) (
            $context->quantityUnit === 'units' ? 1 : $context->quantityMultiplier
        ));
        $riskBudget = Number::of((string) $context->currentCapital)->mul('0.01');
        $quantityNumber = $riskBudget
            ->div($riskDistance->mul($riskMultiplier))
            ->value();
        $quantity = (float) $quantityNumber;
        $takeProfitDistance = $riskDistance->mul(self::TAKE_PROFIT_RISK_MULTIPLE);
        $takeProfitPrice = $isLong
            ? $entryPrice->add($takeProfitDistance)
            : $entryPrice->sub($takeProfitDistance);
        $takeProfit = (float) $takeProfitPrice->value();

        if (! is_finite($quantity) || $quantity <= 0) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        return new StrategyDecision($signal, (float) $stopLossPrice->value(), $takeProfit, $quantity);
    }
}
