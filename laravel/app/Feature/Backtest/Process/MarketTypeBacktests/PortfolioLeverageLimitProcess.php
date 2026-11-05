<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process\MarketTypeBacktests;

use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioManagedOrder;
use Exact\Collection\Vec;
use InvalidArgumentException;

class PortfolioLeverageLimitProcess
{
    /**
     * @param  Vec<PortfolioManagedOrder>  $orders
     * @param  list<array{pair: string, date: string, close: float}>  $prices
     * @param  list<array{date: string, pair: string, order_index: int, amount: float}>  $cashFlows
     * @return Vec<PortfolioManagedOrder>
     */
    public function execute(
        float $initialCapital,
        float $maxGrossLeverage,
        Vec $orders,
        array $prices,
        array $cashFlows = [],
    ): Vec {
        if (! is_finite($initialCapital) || $initialCapital <= 0
            || ! is_finite($maxGrossLeverage) || $maxGrossLeverage <= 0) {
            throw new InvalidArgumentException('Portfolio leverage limits require positive finite capital and leverage.');
        }

        $events = [];
        foreach ($orders as $order) {
            if ($order->entryRejected) {
                continue;
            }
            $firstEntryDate = null;
            $firstEntryFillIndex = null;
            foreach ($order->entryFills as $fillIndex => $fill) {
                $date = $fill->date->getOrNull();
                if ($date === null) {
                    continue;
                }
                if ($firstEntryDate === null || $date < $firstEntryDate) {
                    $firstEntryDate = $date;
                    $firstEntryFillIndex = $fillIndex;
                }
                $events[] = [
                    'date' => $date,
                    'phase' => 2,
                    'pair' => $order->pair,
                    'order_index' => $order->index,
                    'fill_index' => $fillIndex,
                    'type' => 'entry',
                ];
            }
            if ($firstEntryDate !== null) {
                $events[] = [
                    'date' => $firstEntryDate,
                    'phase' => 1,
                    'pair' => $order->pair,
                    'order_index' => $order->index,
                    'fill_index' => $firstEntryFillIndex,
                    'type' => 'candidate',
                ];
            }

            foreach ($order->exitFills as $fillIndex => $fill) {
                $date = $fill->date->getOrNull();
                if ($date === null) {
                    continue;
                }
                $events[] = [
                    'date' => $date,
                    'phase' => 0,
                    'pair' => $order->pair,
                    'order_index' => $order->index,
                    'fill_index' => $fillIndex,
                    'type' => 'exit',
                ];
            }
        }
        foreach ($cashFlows as $cashFlow) {
            $events[] = [
                'date' => $cashFlow['date'],
                'phase' => 1,
                'pair' => $cashFlow['pair'],
                'order_index' => $cashFlow['order_index'],
                'fill_index' => -1,
                'type' => 'cash_flow',
                'amount' => $cashFlow['amount'],
            ];
        }

        foreach ($prices as $price) {
            if (! is_string($price['pair'] ?? null)
                || ! is_string($price['date'] ?? null)
                || ! is_numeric($price['close'] ?? null)
                || ! is_finite((float) $price['close'])
                || (float) $price['close'] <= 0) {
                throw new InvalidArgumentException('Portfolio price points must contain pair, date, and positive close values.');
            }
            $events[] = [
                'date' => $price['date'],
                'phase' => 3,
                'pair' => $price['pair'],
                'order_index' => -1,
                'fill_index' => -1,
                'type' => 'price',
                'close' => (float) $price['close'],
            ];
        }

        usort($events, static function (array $left, array $right) use ($orders): int {
            $dateOrder = $left['date'] <=> $right['date'];
            if ($dateOrder !== 0) {
                return $dateOrder;
            }
            $phaseOrder = $left['phase'] <=> $right['phase'];
            if ($phaseOrder !== 0) {
                return $phaseOrder;
            }
            if ($left['type'] === 'candidate' && $right['type'] === 'candidate') {
                $priorityOrder = $orders->at($right['order_index'])->details->priority
                    <=> $orders->at($left['order_index'])->details->priority;
                if ($priorityOrder !== 0) {
                    return $priorityOrder;
                }
            }

            return [$left['pair'], $left['order_index'], $left['fill_index']]
                <=> [$right['pair'], $right['order_index'], $right['fill_index']];
        });

        $latestPrices = [];
        $positions = [];
        $approved = [];
        $realizedProfitLoss = 0.0;

        foreach ($events as $event) {
            if ($event['type'] === 'price') {
                $latestPrices[$event['pair']] = $event['close'];

                continue;
            }

            $orderIndex = $event['order_index'];
            $order = $orders->at($orderIndex);
            $positionKey = (string) $orderIndex;

            if ($event['type'] === 'cash_flow') {
                if ($approved[$positionKey] ?? false) {
                    $realizedProfitLoss += $event['amount'];
                }

                continue;
            }
            if ($event['type'] === 'exit') {
                if (! ($approved[$positionKey] ?? false)) {
                    continue;
                }
                $fill = $order->exitFills->at($event['fill_index']);
                $realizedProfitLoss += $fill->profitLoss->getOrElse(0.0);
                $position = $positions[$positionKey] ?? null;
                if ($position !== null) {
                    $exitQuantity = $fill->quantity->getOrElse(0.0);
                    $position['quantity'] = max(0.0, $position['quantity'] - $exitQuantity);
                    if ($position['quantity'] <= 0.000000001
                        && $position['reserved_quantity'] <= 0.000000001) {
                        unset($positions[$positionKey]);
                    } else {
                        $positions[$positionKey] = $position;
                    }
                }

                continue;
            }

            if ($event['type'] === 'candidate') {
                $fill = $order->entryFills->at($event['fill_index']);
                $entryPrice = $fill->price->getOrElse($order->details->entryPrice->getOrElse(0.0));
                $candidateNotional = $order->requestedPositionSize
                    * $entryPrice
                    * $order->details->quantityMultiplier;
                $currentGrossExposure = $this->grossExposure($positions, $latestPrices);
                $equity = max(0.0, $initialCapital + $realizedProfitLoss + $this->unrealizedProfitLoss($positions, $latestPrices));
                $exceedsLimit = $currentGrossExposure + $candidateNotional > $equity * $maxGrossLeverage + 0.000000001;
                $hasLowerPriority = false;
                foreach ($positions as $position) {
                    if ($order->details->priority < $position['priority']) {
                        $hasLowerPriority = true;

                        break;
                    }
                }

                if ($exceedsLimit) {
                    $reason = $hasLowerPriority
                        ? 'portfolio_lower_priority_than_open_position'
                        : 'portfolio_leverage_limit';
                    $orders = $this->replaceOrder($orders, $orderIndex, $order->rejected($reason));
                    $approved[$positionKey] = false;

                    continue;
                }

                $approved[$positionKey] = true;
                $positions[$positionKey] = [
                    'pair' => $order->pair,
                    'quantity' => 0.0,
                    'reserved_quantity' => $order->details->entryStatus === 'cancelled'
                        ? 0.0
                        : $order->requestedPositionSize,
                    'entry_price' => $entryPrice,
                    'quantity_multiplier' => $order->details->quantityMultiplier,
                    'pnl_multiplier' => $order->details->pnlMultiplier,
                    'signal' => $order->details->signal->value,
                    'priority' => $order->details->priority,
                ];

                continue;
            }

            if (! ($approved[$positionKey] ?? false)) {
                continue;
            }

            $fill = $order->entryFills->at($event['fill_index']);
            $quantity = $fill->quantity->getOrElse(0.0);
            $fillPrice = $fill->price->getOrElse($order->details->entryPrice->getOrElse(0.0));
            $realizedProfitLoss -= array_sum($fill->tradeCosts);
            $position = $positions[$positionKey];
            $previousQuantity = $position['quantity'];
            $position['entry_price'] = $previousQuantity === 0.0
                ? $fillPrice
                : (($position['entry_price'] * $previousQuantity) + ($fillPrice * $quantity))
                    / ($previousQuantity + $quantity);
            $position['quantity'] += $quantity;
            $position['reserved_quantity'] = max(0.0, $position['reserved_quantity'] - $quantity);
            $positions[$positionKey] = $position;
        }

        return $orders;
    }

    /**
     * @param  array<string, array{
     *     pair: string,
     *     quantity: float,
     *     reserved_quantity: float,
     *     entry_price: float,
     *     quantity_multiplier: float,
     *     pnl_multiplier: float,
     *     signal: string,
     *     priority: int
     * }>  $positions
     * @param  array<string, float>  $latestPrices
     */
    private function grossExposure(array $positions, array $latestPrices): float
    {
        $grossExposure = 0.0;
        foreach ($positions as $position) {
            $markPrice = $latestPrices[$position['pair']] ?? $position['entry_price'];
            $grossExposure += ($position['quantity'] + $position['reserved_quantity'])
                * $markPrice
                * $position['quantity_multiplier'];
        }

        return $grossExposure;
    }

    /**
     * @param  array<string, array{
     *     pair: string,
     *     quantity: float,
     *     reserved_quantity: float,
     *     entry_price: float,
     *     quantity_multiplier: float,
     *     pnl_multiplier: float,
     *     signal: string,
     *     priority: int
     * }>  $positions
     * @param  array<string, float>  $latestPrices
     */
    private function unrealizedProfitLoss(array $positions, array $latestPrices): float
    {
        $profitLoss = 0.0;
        foreach ($positions as $position) {
            $markPrice = $latestPrices[$position['pair']] ?? $position['entry_price'];
            $priceChange = $position['signal'] === 'long'
                ? $markPrice - $position['entry_price']
                : $position['entry_price'] - $markPrice;
            $profitLoss += $priceChange * $position['quantity'] * $position['pnl_multiplier'];
        }

        return $profitLoss;
    }

    /** @param Vec<PortfolioManagedOrder> $orders
     * @return Vec<PortfolioManagedOrder>
     */
    private function replaceOrder(Vec $orders, int $index, PortfolioManagedOrder $order): Vec
    {
        return $orders->slice(0, $index)
            ->concat(Vec::of($order))
            ->concat($orders->slice($index + 1));
    }
}
