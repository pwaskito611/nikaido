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
use Gauss\Number\Number;
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
                $capital = (float) Number::of($capital)
                    ->sub($this->sumCosts($order->entryFills->at($fillIndex)->tradeCosts))
                    ->value();

                continue;
            }
            if ($event->fillIndex->isSome()) {
                $fillIndex = $event->fillIndex->getOrElse(0);
                $fill = $order->exitFills->at($fillIndex);
                $capital = (float) Number::of($capital)->add($fill->profitLoss->getOrElse(0.0))->value();
                $isLastFillOfClosedOrder = $order->closeDate->isSome()
                    && $fillIndex === $order->exitFills->count() - 1;
                $orders = $this->replaceOrder(
                    $orders,
                    $event->orderIndex,
                    $order->withExitCapital($fillIndex, $capital, $isLastFillOfClosedOrder),
                );
            } else {
                $capital = (float) Number::of($capital)->add($order->profitLoss->getOrElse(0.0))->value();
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
        $activeRisk = Vec::empty();
        $sequence = 0;

        foreach ($orders as $order) {
            $unitRisk = $order->riskPerUnit();
            if (! is_finite($unitRisk)) {
                throw new InvalidArgumentException('A portfolio-managed order has a non-finite stop-loss risk per quantity unit.');
            }

            $riskPerUnit = $riskPerUnit->append($unitRisk);
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

                return [$left->pair, $left->orderId, $left->sequence]
                    <=> [$right->pair, $right->orderId, $right->sequence];
            },
        ));

        $capital = $initialCapital;
        $reservedRisk = Number::of(0);
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
                    $capital = (float) Number::of($capital)->add($fillProfitLoss)->value();
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
                    $capital = (float) Number::of($capital)->add($order->profitLoss->getOrElse(0.0))->value();
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
                $reservedRisk = $reservedRisk->sub($releaseRisk);
                if ($reservedRisk->compare(0) < 0) {
                    $reservedRisk = Number::of(0);
                }
            }

            $entryEvents = $dateEvents->filter(
                static fn (PortfolioCapitalEvent $event): bool => ! $event->isExit && ! $event->isEntryFill,
            );
            $totalDemand = Number::of(0);
            $entryDemands = [];
            $entrySizingFactors = [];
            foreach ($entryEvents as $event) {
                $order = $orders->at($event->orderIndex);
                $riskBasis = $unfilledOrderPolicy === UnfilledOrderPolicy::Cancel && $order->cancelledEntryQuantity > 0
                    ? $order->riskAmount
                    : $order->requestedRiskAmount;
                $localCapital = $order->capitalAtOpen->getOrNull();
                if ($localCapital !== null && $localCapital <= 0) {
                    throw new InvalidArgumentException('A portfolio-managed order must have positive local capital at entry.');
                }
                $sizingFactor = $localCapital !== null
                    ? (float) Number::of($capital)->div($localCapital)->value()
                    : 1.0;
                $demand = (float) Number::of($riskBasis)->mul($sizingFactor)->value();
                $entryDemands[$event->orderIndex] = $demand;
                $entrySizingFactors[$event->orderIndex] = $sizingFactor;
                $totalDemand = $totalDemand->add($demand);
            }
            $availableCapital = Number::of($capital)->sub($reservedRisk);
            if ($availableCapital->compare(0) < 0) {
                $availableCapital = Number::of(0);
            }
            $fixedEntryCosts = Number::of(0);
            $variableEntryCosts = Number::of(0);
            foreach ($entryEvents as $event) {
                foreach ($orders->at($event->orderIndex)->entryTradeCosts() as $component => $cost) {
                    if (in_array($component, $perOrderCostComponents, true)) {
                        $fixedEntryCosts = $fixedEntryCosts->add($cost);
                    } else {
                        $variableEntryCosts = $variableEntryCosts->add(
                            Number::of($cost)->mul($entrySizingFactors[$event->orderIndex]),
                        );
                    }
                }
            }
            $availableAfterFixedFees = $availableCapital->sub($fixedEntryCosts);
            if ($availableAfterFixedFees->compare(0) < 0) {
                $availableAfterFixedFees = Number::of(0);
            }
            $allocationDemand = $totalDemand->add($variableEntryCosts);
            $sharedFactor = $allocationDemand->compare($availableAfterFixedFees) > 0
                && $allocationDemand->compare(0) > 0
                ? (float) $availableAfterFixedFees->div($allocationDemand)->value()
                : 1.0;

            foreach ($entryEvents as $event) {
                $demand = $entryDemands[$event->orderIndex];
                $sizingFactor = $entrySizingFactors[$event->orderIndex];
                $allocationFactor = $sizingFactor * $sharedFactor;
                $allocatedRisk = $demand * $sharedFactor;
                $order = $orders->at($event->orderIndex);

                if ($allocationFactor === 0.0 && $order->requestedRiskAmount > 0) {
                    $orders = $this->replaceOrder(
                        $orders,
                        $event->orderIndex,
                        $order
                            ->withAllocation($allocationFactor, $demand, $allocatedRisk, $capital)
                            ->rejected(),
                    );

                    continue;
                }

                $allocatedOrder = $order->withAllocation(
                    $allocationFactor,
                    $demand,
                    $allocatedRisk,
                    $capital,
                    $perOrderCostComponents,
                );
                $orders = $this->replaceOrder($orders, $event->orderIndex, $allocatedOrder);
                $activeRisk = $this->replaceRisk($activeRisk, $event->orderIndex, $allocatedRisk);
                $reservedRisk = $reservedRisk->add($allocatedRisk);
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
                $capital = (float) Number::of($capital)
                    ->sub($this->sumCosts($order->entryFills->at($fillIndex)->tradeCosts))
                    ->value();
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

    /** @param array<string, float> $costs */
    private function sumCosts(array $costs): Number
    {
        return array_reduce(
            $costs,
            static fn (Number $total, float $cost): Number => $total->add($cost),
            Number::of(0),
        );
    }
}
