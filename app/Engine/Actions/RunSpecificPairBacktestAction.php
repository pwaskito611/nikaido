<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use App\Engine\Config\SpecificPairTestConfig;
use App\Engine\Models\BacktestOrderHistoryEntry;
use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Strategy\Enums\StrategySignal;
use App\Strategy\Strategy;
use Exact\Collection\Vec;
use UnexpectedValueException;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
        private readonly Strategy $strategy,
    ) {}

    /**
     * @return array{
     *     results: Vec<array{candle: MarketPrice, result: bool, signal: string, stop_loss: ?float, take_profit: ?float}>,
     *     order_history: Vec<BacktestOrderHistoryEntry>,
     *     initial_capital: float,
     *     ending_capital: float
     * }
     */
    public function execute(SpecificPairTestConfig $config): array
    {
        $pair = $this->marketPairRepository->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
            $config->timeframeTypeId,
        );

        $decisions = $this->strategy->execute($pair->prices);
        $results = Vec::empty();
        $orderHistory = Vec::empty();
        $openOrderIndex = null;
        $currentCapital = $config->initialCapital;

        foreach ($pair->prices as $index => $candle) {
            if ($candle->price_date < $config->from) {
                continue;
            }

            $decision = $this->isActive($candle, $config)
                ? $decisions->at($index)
                : null;
            $hadOpenOrder = $openOrderIndex !== null;

            if ($openOrderIndex !== null) {
                $order = $orderHistory->at($openOrderIndex);
                $this->updateClosestToStopLoss($order, $candle);
                $this->updateClosestToTakeProfit($order, $candle);

                $closeReason = $this->getPriceExitReason($order, $candle);

                if ($closeReason === null && $decision?->signal === StrategySignal::Close) {
                    $closeReason = 'close';
                }

                if ($closeReason !== null) {
                    $order->closeDate = $candle->price_date->format('Y-m-d H:i:s');
                    $order->closeReason = $closeReason;
                    $order->closePrice = $this->closePrice($order, $candle, $closeReason);
                    if ($order->closePrice === null) {
                        throw new UnexpectedValueException('Cannot calculate trade profit or loss without a closing price.');
                    }
                    $isLong = $order->signal === StrategySignal::Long->value;
                    $priceMove = $isLong
                        ? $order->closePrice - $order->entryPrice
                        : $order->entryPrice - $order->closePrice;
                    $order->profitLoss = $priceMove * $order->positionSize;
                    $currentCapital += $order->profitLoss;
                    $order->capitalAfterClose = $currentCapital;
                    $openOrderIndex = null;
                }
            }

            if ($decision !== null) {
                $results = $results->append([
                    'candle' => $candle,
                    'result' => $decision->signal !== StrategySignal::None,
                    'signal' => $decision->signal->value,
                    'stop_loss' => $decision->stopLoss,
                    'take_profit' => $decision->takeProfit,
                ]);
            }

            if (! $hadOpenOrder && $openOrderIndex === null && $decision !== null
                && in_array($decision->signal, [StrategySignal::Long, StrategySignal::Short], true)) {
                $entry = $this->entryPrice($candle, $decision->signal);

                if ($decision->stopLoss === null || $decision->takeProfit === null) {
                    throw new UnexpectedValueException('An entry signal must include stop-loss and take-profit values.');
                }

                $riskDistance = abs($entry['price'] - $decision->stopLoss);
                if ($riskDistance <= 0) {
                    throw new UnexpectedValueException('Cannot size an order when entry and stop-loss prices are equal.');
                }
                $riskAmount = $currentCapital * ($config->riskPerTradePercent / 100);
                if ($riskAmount <= 0) {
                    throw new UnexpectedValueException('Cannot open an order because available capital is no longer positive.');
                }

                $orderHistory = $orderHistory->append(new BacktestOrderHistoryEntry(
                    id: $orderHistory->count() + 1,
                    signal: $decision->signal->value,
                    openDate: $candle->price_date->format('Y-m-d H:i:s'),
                    entryPrice: $entry['price'],
                    entryPriceSource: $entry['source'],
                    stopLoss: $decision->stopLoss,
                    takeProfit: $decision->takeProfit,
                    riskAmount: $riskAmount,
                    positionSize: $riskAmount / $riskDistance,
                    capitalAtOpen: $currentCapital,
                ));
                $openOrderIndex = $orderHistory->count() - 1;
            }
        }

        return [
            'results' => $results,
            'order_history' => $orderHistory,
            'initial_capital' => $config->initialCapital,
            'ending_capital' => $currentCapital,
        ];
    }

    private function updateClosestToStopLoss(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $closeQuote = $isLong ? $candle->bid_close : $candle->ask_close;
        $stopQuote = $isLong ? $candle->bid_low : $candle->ask_high;
        $candidates = [
            is_numeric($closeQuote) ? ($isLong ? 'bid_close' : 'ask_close') : 'close' => is_numeric($closeQuote)
                ? $closeQuote
                : $candle->close,
            is_numeric($stopQuote) ? ($isLong ? 'bid_low' : 'ask_high') : ($isLong ? 'low' : 'high') => is_numeric($stopQuote)
                ? $stopQuote
                : ($isLong ? $candle->low : $candle->high),
        ];

        foreach ($candidates as $source => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $price = (float) $value;
            $distance = round(abs($price - $order->stopLoss), 6);
            $previous = $order->closestToStopLoss;

            if ($previous === null || $distance < $previous['distance']) {
                $order->closestToStopLoss = [
                    'price' => $price,
                    'date' => $candle->price_date->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'distance' => $distance,
                ];
            }
        }
    }

    private function updateClosestToTakeProfit(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $closeQuote = $isLong ? $candle->bid_close : $candle->ask_close;
        $takeProfitQuote = $isLong ? $candle->bid_high : $candle->ask_low;
        $closeSource = is_numeric($closeQuote) ? ($isLong ? 'bid_close' : 'ask_close') : 'close';
        $takeProfitSource = is_numeric($takeProfitQuote)
            ? ($isLong ? 'bid_high' : 'ask_low')
            : ($isLong ? 'high' : 'low');
        $candidates = [
            $closeSource => is_numeric($closeQuote) ? $closeQuote : $candle->close,
            $takeProfitSource => is_numeric($takeProfitQuote)
                ? $takeProfitQuote
                : ($isLong ? $candle->high : $candle->low),
        ];

        foreach ($candidates as $source => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $price = (float) $value;
            $distance = round(abs($price - $order->takeProfit), 6);
            $previous = $order->closestToTakeProfit;

            if ($previous === null || $distance < $previous['distance']) {
                $order->closestToTakeProfit = [
                    'price' => $price,
                    'date' => $candle->price_date->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'distance' => $distance,
                ];
            }
        }
    }

    private function getPriceExitReason(BacktestOrderHistoryEntry $order, MarketPrice $candle): ?string
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $stopPrice = $isLong ? $candle->bid_low : $candle->ask_high;
        $stopFallback = $isLong ? $candle->low : $candle->high;
        $takeProfitPrice = $isLong ? $candle->bid_high : $candle->ask_low;
        $takeProfitFallback = $isLong ? $candle->high : $candle->low;
        $stopPrice = is_numeric($stopPrice) ? $stopPrice : $stopFallback;
        $takeProfitPrice = is_numeric($takeProfitPrice) ? $takeProfitPrice : $takeProfitFallback;

        if (is_numeric($stopPrice) && ($isLong
            ? (float) $stopPrice <= $order->stopLoss
            : (float) $stopPrice >= $order->stopLoss)) {
            // OHLC cannot show which level was touched first, so prefer the adverse exit.
            return 'stop_loss';
        }

        if (is_numeric($takeProfitPrice) && ($isLong
            ? (float) $takeProfitPrice >= $order->takeProfit
            : (float) $takeProfitPrice <= $order->takeProfit)) {
            return 'take_profit';
        }

        return null;
    }

    private function closePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle, string $reason): ?float
    {
        if ($reason === 'stop_loss') {
            return $this->marketClosePrice($order, $candle);
        }

        if ($reason === 'take_profit') {
            return $order->takeProfit;
        }

        return $this->marketClosePrice($order, $candle);
    }

    private function marketClosePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle): ?float
    {
        $quote = $order->signal === StrategySignal::Long->value
            ? $candle->bid_close
            : $candle->ask_close;
        $close = is_numeric($quote) ? $quote : $candle->close;

        return is_numeric($close) ? (float) $close : null;
    }

    /**
     * @return array{price: float, source: string}
     */
    private function entryPrice(MarketPrice $candle, StrategySignal $signal): array
    {
        $quoteName = $signal === StrategySignal::Long ? 'ask_open' : 'bid_open';
        $quote = $candle->{$quoteName};

        if (is_numeric($quote)) {
            return ['price' => (float) $quote, 'source' => $quoteName];
        }

        throw new UnexpectedValueException("Cannot open an order because the candle has no usable {$quoteName} price.");
    }

    private function isActive(MarketPrice $candle, SpecificPairTestConfig $config): bool
    {
        $timestamp = $candle->price_date;

        if (in_array((int) $timestamp->format('N'), $config->excludedDays, true)) {
            return false;
        }

        if ($config->activeFrom === false) {
            return true;
        }

        $time = $timestamp->format('H:i');

        if ($config->activeFrom <= $config->activeUntil) {
            return $time >= $config->activeFrom && $time <= $config->activeUntil;
        }

        return $time >= $config->activeFrom || $time <= $config->activeUntil;
    }
}
