<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process\MarketTypeBacktests;

use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioCapitalEvent;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioCapitalSynchronizationResult;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioManagedOrder;
use Exact\Collection\SortedSet;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use InvalidArgumentException;

class PortfolioCapitalSynchronizationProcess
{
    /**
     * @param  Vec<PortfolioManagedOrder>  $orders
     */
    public function applySeparateCapitalTimeline(float $initialCapital, Vec $orders): PortfolioCapitalSynchronizationResult
    {
        $events = Vec::empty();
        $sequence = 0;

        foreach ($orders as $order) {
            foreach ($order->entryFills as $fillIndex => $fill) {
                if ($fill->date->isSome()) {
                    $events = $events->append(new PortfolioCapitalEvent(
                        date: $fill->date->getOrElse(''),
                        isExit: false,
                        orderIndex: $order->index,
                        fillIndex: Option::some($fillIndex),
                        pair: $order->pair,
                        orderId: $order->id,
                        sequence: $sequence++,
                        isEntryFill: true,
                    ));
                }
            }
            foreach ($order->exitFills as $fillIndex => $fill) {
                if ($fill->date->isSome() && $fill->profitLoss->isSome()) {
                    $events = $events->append(new PortfolioCapitalEvent(
                        date: $fill->date->getOrElse(''),
                        isExit: true,
                        orderIndex: $order->index,
                        fillIndex: Option::some($fillIndex),
                        pair: $order->pair,
                        orderId: $order->id,
                        sequence: $sequence++,
                    ));
                }
            }

            if ($order->exitFills->isEmpty() && $order->closeDate->isSome() && $order->profitLoss->isSome()) {
                $events = $events->append(new PortfolioCapitalEvent(
                    date: $order->closeDate->getOrElse(''),
                    isExit: true,
                    orderIndex: $order->index,
                    fillIndex: Option::none(),
                    pair: $order->pair,
                    orderId: $order->id,
                    sequence: $sequence++,
                ));
            }
        }

        $orderedEvents = Vec::fromIterable(SortedSet::fromIterable(
            $events,
            static function (PortfolioCapitalEvent $left, PortfolioCapitalEvent $right): int {
                $dateOrder = $left->date <=> $right->date;
                if ($dateOrder !== 0) {
                    return $dateOrder;
                }

                return [$left->pair, $left->orderId, $left->sequence]
                    <=> [$right->pair, $right->orderId, $right->sequence];
            },
        ));

        $capital = $initialCapital;
        foreach ($orderedEvents as $event) {
            $order = $orders->at($event->orderIndex);
            if (! $event->isExit) {
                $fillIndex = $event->fillIndex->getOrElseThrow(
                    new InvalidArgumentException('An entry-cost event requires an entry fill index.'),
                );
                $capital -= array_sum($order->entryFills->at($fillIndex)->tradeCosts);

                continue;
            }
            if ($event->fillIndex->isSome()) {
                $fillIndex = $event->fillIndex->getOrElse(0);
                $fill = $order->exitFills->at($fillIndex);
                $capital += $fill->profitLoss->getOrElse(0.0);
                $isLastFillOfClosedOrder = $order->closeDate->isSome()
                    && $fillIndex === $order->exitFills->count() - 1;
                $orders = $this->replaceOrder(
                    $orders,
                    $event->orderIndex,
                    $order->withExitCapital($fillIndex, $capital, $isLastFillOfClosedOrder),
                );
            } else {
                $capital += $order->profitLoss->getOrElse(0.0);
                $orders = $this->replaceOrder($orders, $event->orderIndex, $order->withCapitalAfterClose($capital));
            }
        }

        return new PortfolioCapitalSynchronizationResult($orders, $capital);
    }

    /**
     * @param  Vec<PortfolioManagedOrder>  $orders
     */
    public function execute(
        float $initialCapital,
        Vec $orders,
        UnfilledOrderPolicy $unfilledOrderPolicy = UnfilledOrderPolicy::Keep,
        array $perOrderCostComponents = [],
    ): PortfolioCapitalSynchronizationResult {
        if (! is_finite($initialCapital) || $initialCapital <= 0) {
            throw new InvalidArgumentException('Shared portfolio capital must be a positive finite number.');
        }
        $events = Vec::empty();
        $riskPerUnit = Vec::empty();
        $riskDemand = Vec::empty();
        $activeRisk = Vec::empty();
        $sequence = 0;

        foreach ($orders as $order) {
            $unitRisk = $order->riskPerUnit();
            if (! is_finite($unitRisk)) {
                throw new InvalidArgumentException('A portfolio-managed order has a non-finite stop-loss risk per quantity unit.');
            }

            $riskPerUnit = $riskPerUnit->append($unitRisk);
            $demand = $unfilledOrderPolicy === UnfilledOrderPolicy::Cancel && $order->cancelledEntryQuantity > 0
                ? $order->riskAmount
                : $order->requestedRiskAmount;
            $riskDemand = $riskDemand->append($demand);
            $activeRisk = $activeRisk->append(0.0);
            $events = $events->append(new PortfolioCapitalEvent(
                date: $order->openDate,
                isExit: false,
                orderIndex: $order->index,
                fillIndex: Option::none(),
                pair: $order->pair,
                orderId: $order->id,
                sequence: $sequence++,
            ));

            foreach ($order->entryFills as $fillIndex => $fill) {
                if ($fill->date->isSome()) {
                    $events = $events->append(new PortfolioCapitalEvent(
                        date: $fill->date->getOrElse(''),
                        isExit: false,
                        orderIndex: $order->index,
                        fillIndex: Option::some($fillIndex),
                        pair: $order->pair,
                        orderId: $order->id,
                        sequence: $sequence++,
                        isEntryFill: true,
                    ));
                }
            }

            foreach ($order->exitFills as $fillIndex => $fill) {
                if ($fill->date->isSome() && $fill->profitLoss->isSome()) {
                    $events = $events->append(new PortfolioCapitalEvent(
                        date: $fill->date->getOrElse(''),
                        isExit: true,
                        orderIndex: $order->index,
                        fillIndex: Option::some($fillIndex),
                        pair: $order->pair,
                        orderId: $order->id,
                        sequence: $sequence++,
                    ));
                }
            }

            if ($order->exitFills->isEmpty() && $order->closeDate->isSome() && $order->profitLoss->isSome()) {
                $events = $events->append(new PortfolioCapitalEvent(
                    date: $order->closeDate->getOrElse(''),
                    isExit: true,
                    orderIndex: $order->index,
                    fillIndex: Option::none(),
                    pair: $order->pair,
                    orderId: $order->id,
                    sequence: $sequence++,
                ));
            }
        }

        $orderedEvents = Vec::fromIterable(SortedSet::fromIterable(
            $events,
            static function (PortfolioCapitalEvent $left, PortfolioCapitalEvent $right): int {
                $dateOrder = $left->date <=> $right->date;
                if ($dateOrder !== 0) {
                    return $dateOrder;
                }
                if ($left->isExit !== $right->isExit) {
                    return $left->isExit ? -1 : 1;
                }
                if ($left->isEntryFill !== $right->isEntryFill) {
                    return $left->isEntryFill ? 1 : -1;
                }
                if ($left->isExit) {
                    return $left->sequence <=> $right->sequence;
                }

                return [$left->pair, $left->orderId, $left->sequence]
                    <=> [$right->pair, $right->orderId, $right->sequence];
            },
        ));

        $capital = $initialCapital;
        $reservedRisk = 0.0;
        $eventOffset = 0;
        while ($eventOffset < $orderedEvents->count()) {
            $date = $orderedEvents->at($eventOffset)->date;
            $dateEvents = Vec::empty();
            while ($eventOffset < $orderedEvents->count()
                && $orderedEvents->at($eventOffset)->date === $date) {
                $dateEvents = $dateEvents->append($orderedEvents->at($eventOffset));
                $eventOffset++;
            }

            foreach ($dateEvents->filter(static fn (PortfolioCapitalEvent $event): bool => $event->isExit) as $event) {
                $order = $orders->at($event->orderIndex);
                if ($order->entryRejected) {
                    continue;
                }

                $orderActiveRisk = $activeRisk->at($event->orderIndex);
                if ($event->fillIndex->isSome()) {
                    $fillIndex = $event->fillIndex->getOrElse(0);
                    $fill = $order->exitFills->at($fillIndex);
                    $fillProfitLoss = $fill->profitLoss->getOrElse(0.0);
                    $capital += $fillProfitLoss;
                    $releaseRisk = min(
                        $orderActiveRisk,
                        $riskPerUnit->at($event->orderIndex) * $fill->quantity->getOrElse(0.0),
                    );
                    $isLastFillOfClosedOrder = $order->closeDate->isSome()
                        && $fillIndex === $order->exitFills->count() - 1;
                    $orders = $this->replaceOrder(
                        $orders,
                        $event->orderIndex,
                        $order->withExitCapital($fillIndex, $capital, $isLastFillOfClosedOrder),
                    );
                    if ($isLastFillOfClosedOrder) {
                        $releaseRisk = $orderActiveRisk;
                    }
                } else {
                    $capital += $order->profitLoss->getOrElse(0.0);
                    $releaseRisk = $orderActiveRisk;
                    $orders = $this->replaceOrder(
                        $orders,
                        $event->orderIndex,
                        $order->withCapitalAfterClose($capital),
                    );
                }

                $activeRisk = $this->replaceRisk(
                    $activeRisk,
                    $event->orderIndex,
                    max(0.0, $orderActiveRisk - $releaseRisk),
                );
                $reservedRisk = max(0.0, $reservedRisk - $releaseRisk);
            }

            $entryEvents = $dateEvents->filter(
                static fn (PortfolioCapitalEvent $event): bool => ! $event->isExit && ! $event->isEntryFill,
            );
            $totalDemand = $entryEvents->foldLeft(
                0.0,
                fn (float $total, PortfolioCapitalEvent $event): float => $total + $riskDemand->at($event->orderIndex),
            );
            $availableCapital = max(0.0, $capital - $reservedRisk);
            $fixedEntryCosts = 0.0;
            $variableEntryCosts = 0.0;
            foreach ($entryEvents as $event) {
                foreach ($orders->at($event->orderIndex)->entryTradeCosts() as $component => $cost) {
                    if (in_array($component, $perOrderCostComponents, true)) {
                        $fixedEntryCosts += $cost;
                    } else {
                        $variableEntryCosts += $cost;
                    }
                }
            }
            $availableAfterFixedFees = max(0.0, $availableCapital - $fixedEntryCosts);
            $allocationDemand = $totalDemand + $variableEntryCosts;
            $sharedFactor = $allocationDemand > $availableAfterFixedFees && $allocationDemand > 0
                ? $availableAfterFixedFees / $allocationDemand
                : 1.0;

            foreach ($entryEvents as $event) {
                $demand = $riskDemand->at($event->orderIndex);
                $factor = $demand > 0 ? $sharedFactor : 1.0;
                $allocatedRisk = $demand * $factor;
                $order = $orders->at($event->orderIndex);

                if ($factor === 0.0 && $demand > 0) {
                    $orders = $this->replaceOrder(
                        $orders,
                        $event->orderIndex,
                        $order
                            ->withAllocation($factor, $demand, $allocatedRisk, $capital)
                            ->rejected(),
                    );

                    continue;
                }

                $allocatedOrder = $order->withAllocation(
                    $factor,
                    $demand,
                    $allocatedRisk,
                    $capital,
                    $perOrderCostComponents,
                );
                $orders = $this->replaceOrder($orders, $event->orderIndex, $allocatedOrder);
                $activeRisk = $this->replaceRisk($activeRisk, $event->orderIndex, $allocatedRisk);
                $reservedRisk += $allocatedRisk;
            }

            foreach ($dateEvents->filter(
                static fn (PortfolioCapitalEvent $event): bool => ! $event->isExit && $event->isEntryFill,
            ) as $event) {
                $order = $orders->at($event->orderIndex);
                if ($order->entryRejected) {
                    continue;
                }
                $fillIndex = $event->fillIndex->getOrElseThrow(
                    new InvalidArgumentException('An entry-cost event requires an entry fill index.'),
                );
                $capital -= array_sum($order->entryFills->at($fillIndex)->tradeCosts);
            }
        }

        return new PortfolioCapitalSynchronizationResult($orders, $capital);
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

    /** @param Vec<float> $risks
     * @return Vec<float>
     */
    private function replaceRisk(Vec $risks, int $index, float $risk): Vec
    {
        return $risks->slice(0, $index)
            ->concat(Vec::of($risk))
            ->concat($risks->slice($index + 1));
    }
}
