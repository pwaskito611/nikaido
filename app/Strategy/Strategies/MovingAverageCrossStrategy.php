<?php

namespace App\Strategy\Strategies;

use App\Engine\Models\MarketPrice;
use App\Strategy\Enums\StrategySignal;
use App\Strategy\Models\StrategyDecision;
use Exact\Collection\Vec;
use Gauss\TimeSeries\Smoothing\MovingAverage;
use Gauss\TimeSeries\TimeSeries;
use UnexpectedValueException;

class MovingAverageCrossStrategy
{
    private const FAST_EMA_PERIOD = 20;

    private const SLOW_EMA_PERIOD = 50;

    private const TREND_MA_PERIOD = 200;

    private const TAKE_PROFIT_RISK_MULTIPLE = 2.0;

    /**
     * @param  iterable<MarketPrice>  $candles  Must be in chronological order and include warm-up candles.
     * @return Vec<StrategyDecision>
     */
    public function execute(iterable $candles): Vec
    {
        $candleList = [];
        $closes = [];

        foreach ($candles as $candle) {
            if (! is_numeric($candle->close)) {
                throw new UnexpectedValueException('Every candle must have a numeric close for MA cross analysis.');
            }

            $candleList[] = $candle;
            $closes[] = (float) $candle->close;
        }

        if (count($closes) < self::TREND_MA_PERIOD) {
            return Vec::fromArray(array_fill(
                0,
                count($closes),
                new StrategyDecision(StrategySignal::None, null, null),
            ));
        }

        $fastEma = $this->exponentialMovingAverage($closes, self::FAST_EMA_PERIOD);
        $slowEma = $this->exponentialMovingAverage($closes, self::SLOW_EMA_PERIOD);
        $trendMa = MovingAverage::smooth(TimeSeries::of($closes), self::TREND_MA_PERIOD, false);
        $decisions = Vec::empty();

        foreach ($candleList as $index => $candle) {
            if ($index < self::TREND_MA_PERIOD - 1) {
                $decisions = $decisions->append(new StrategyDecision(StrategySignal::None, null, null));

                continue;
            }

            $crossedUp = $fastEma[$index - 1] <= $slowEma[$index - 1]
                && $fastEma[$index] > $slowEma[$index];
            $crossedDown = $fastEma[$index - 1] >= $slowEma[$index - 1]
                && $fastEma[$index] < $slowEma[$index];
            $trendAverage = (float) $trendMa->valueAt($index)->value();
            $close = $closes[$index];

            $decision = $crossedUp && $close > $trendAverage
                ? $this->entryDecision($candle, StrategySignal::Long)
                : ($crossedDown && $close < $trendAverage
                    ? $this->entryDecision($candle, StrategySignal::Short)
                    : new StrategyDecision(StrategySignal::None, null, null));

            $decisions = $decisions->append($decision);
        }

        return $decisions;
    }

    /**
     * @param  list<float>  $prices
     * @return list<float>
     */
    private function exponentialMovingAverage(array $prices, int $period): array
    {
        $alpha = 2 / ($period + 1);
        $ema = [];
        $previous = null;

        foreach ($prices as $index => $price) {
            $previous = $previous === null
                ? $price
                : $alpha * $price + (1 - $alpha) * $previous;
            $ema[$index] = $previous;
        }

        return $ema;
    }

    private function entryDecision(MarketPrice $candle, StrategySignal $signal): StrategyDecision
    {
        $quoteStopLoss = $signal === StrategySignal::Long ? $candle->bid_low : $candle->ask_high;
        $fallbackStopLoss = $signal === StrategySignal::Long ? $candle->low : $candle->high;
        $stopLoss = is_numeric($quoteStopLoss) ? $quoteStopLoss : $fallbackStopLoss;
        $entryQuote = $signal === StrategySignal::Long ? $candle->ask_open : $candle->bid_open;
        $entry = is_numeric($entryQuote) ? $entryQuote : $candle->open;

        if (! is_numeric($stopLoss) || ! is_numeric($entry)) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        $stopLoss = (float) $stopLoss;
        $risk = $signal === StrategySignal::Long
            ? $entry - $stopLoss
            : $stopLoss - $entry;

        if ($risk <= 0) {
            return new StrategyDecision(StrategySignal::None, null, null);
        }

        $takeProfit = $signal === StrategySignal::Long
            ? $entry + self::TAKE_PROFIT_RISK_MULTIPLE * $risk
            : $entry - self::TAKE_PROFIT_RISK_MULTIPLE * $risk;

        return new StrategyDecision($signal, $stopLoss, $takeProfit);
    }
}
