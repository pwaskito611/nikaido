<?php

namespace App\Engine\Actions;

use App\Engine\Enums\StrategySignal;
use App\Engine\Models\BacktestOrderHistoryEntry;
use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Services\StrategyClient;
use App\Strategy\Config\SpecificPairTestConfig;
use Exact\Collection\Vec;
use UnexpectedValueException;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
        private readonly StrategyClient $strategyClient,
    ) {}

    /**
     * @return array{
     *     results: Vec<array{candle: MarketPrice, result: bool, signal: string, stop_loss: ?float, take_profit: ?float}>,
     *     order_history: Vec<BacktestOrderHistoryEntry>
     * }
     */
    public function execute(SpecificPairTestConfig $config): array
    {
        $pair = $this->marketPairRepository->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
        );

        $results = Vec::empty();
        $orderHistory = Vec::empty();
        $openOrderIndex = null;

        foreach ($pair->prices as $candle) {
            $decision = $this->isActive($candle, $config)
                ? $this->strategyClient->execute()
                : null;
            $hadOpenOrder = $openOrderIndex !== null;

            if ($openOrderIndex !== null) {
                $order = $orderHistory->at($openOrderIndex);
                $this->updateClosestToStopLoss($order, $candle);

                $closeReason = $this->getPriceExitReason($order, $candle);

                if ($closeReason === null && $decision?->signal === StrategySignal::Close) {
                    $closeReason = 'close';
                }

                if ($closeReason !== null) {
                    $order->closeDate = $candle->price_date->format('Y-m-d H:i:s');
                    $order->closeReason = $closeReason;
                    $order->closePrice = $this->closePrice($order, $candle, $closeReason);
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

                $orderHistory = $orderHistory->append(new BacktestOrderHistoryEntry(
                    id: $orderHistory->count() + 1,
                    signal: $decision->signal->value,
                    openDate: $candle->price_date->format('Y-m-d H:i:s'),
                    entryPrice: $entry['price'],
                    entryPriceSource: $entry['source'],
                    stopLoss: $decision->stopLoss,
                    takeProfit: $decision->takeProfit,
                ));
                $openOrderIndex = $orderHistory->count() - 1;
            }
        }

        return [
            'results' => $results,
            'order_history' => $orderHistory,
        ];
    }

    private function updateClosestToStopLoss(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $candidates = $isLong
            ? ['bid' => $candle->bid, 'low' => $candle->low]
            : ['ask' => $candle->ask, 'high' => $candle->high];

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

    private function getPriceExitReason(BacktestOrderHistoryEntry $order, MarketPrice $candle): ?string
    {
        if ($order->signal === StrategySignal::Long->value) {
            $stopLossHit = is_numeric($candle->low) && (float) $candle->low <= $order->stopLoss;
            $takeProfitHit = is_numeric($candle->high) && (float) $candle->high >= $order->takeProfit;
        } else {
            $stopLossHit = is_numeric($candle->high) && (float) $candle->high >= $order->stopLoss;
            $takeProfitHit = is_numeric($candle->low) && (float) $candle->low <= $order->takeProfit;
        }

        if ($stopLossHit) {
            // OHLC cannot show which level was touched first, so prefer the adverse exit.
            return 'stop_loss';
        }

        return $takeProfitHit ? 'take_profit' : null;
    }

    private function closePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle, string $reason): ?float
    {
        if ($reason === 'stop_loss') {
            return $order->stopLoss;
        }

        if ($reason === 'take_profit') {
            return $order->takeProfit;
        }

        $quote = $order->signal === StrategySignal::Long->value ? $candle->bid : $candle->ask;
        $price = is_numeric($quote) ? $quote : $candle->close;

        return is_numeric($price) ? (float) $price : null;
    }

    /**
     * @return array{price: float, source: string}
     */
    private function entryPrice(MarketPrice $candle, StrategySignal $signal): array
    {
        $quoteName = $signal === StrategySignal::Long ? 'ask' : 'bid';
        $quote = $candle->{$quoteName};

        if (is_numeric($quote)) {
            return ['price' => (float) $quote, 'source' => $quoteName];
        }

        if (is_numeric($candle->open)) {
            return ['price' => (float) $candle->open, 'source' => 'open'];
        }

        throw new UnexpectedValueException('Cannot open an order because the candle has no usable entry price.');
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
