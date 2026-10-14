<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

use Exact\Collection\SortedSet;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use Exact\Type\Tuple\Pair;

final class BacktestClusterAnalysisDomain
{
    public function execute(
        BacktestAnalysisInput $input,
        BacktestAnalysisOptions $options,
    ): BacktestClusterAnalysisResult {
        $decisions = $this->selectDecisions(
            $input->decisions,
            $options->clusterCount,
            $options->selectedCluster,
        );
        $orders = $this->selectOrders(
            $input->orders,
            $decisions,
            $options->clusterCount,
        );
        $summary = $this->summarizeCluster(
            $input->initialCapital,
            $input->orders,
            $decisions,
            $orders,
            $options->clusterCount,
        );

        return new BacktestClusterAnalysisResult(
            decisions: $decisions,
            orders: $orders,
            summary: $summary->first(),
            capitalChart: $summary->second(),
        );
    }

    /** @param Vec<BacktestAnalysisDecision> $allDecisions */
    private function selectDecisions(Vec $allDecisions, Option $clusterCount, int $selectedCluster): Vec
    {
        if ($clusterCount->isNone()) {
            return $allDecisions;
        }

        $clusterTotal = $clusterCount->getOrElseThrow(new \LogicException('A cluster count was expected.'));
        $totalDecisions = $allDecisions->count();
        $start = (int) floor(($selectedCluster - 1) * $totalDecisions / $clusterTotal);
        $end = (int) floor($selectedCluster * $totalDecisions / $clusterTotal);

        return $allDecisions->slice($start, $end - $start);
    }

    /**
     * @param  Vec<BacktestAnalysisOrder>  $allOrders
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     * @return Vec<BacktestAnalysisOrder>
     */
    private function selectOrders(Vec $allOrders, Vec $decisions, Option $clusterCount): Vec
    {
        if ($clusterCount->isNone()) {
            return $allOrders;
        }

        if ($decisions->isEmpty()) {
            return Vec::empty();
        }

        $firstTimestamp = $decisions->foldLeft(
            $decisions->first()->timestamp,
            static fn (BacktestAnalysisTimestamp $first, BacktestAnalysisDecision $decision): BacktestAnalysisTimestamp => $decision->timestamp->isBefore($first)
                ? $decision->timestamp
                : $first,
        );
        $lastTimestamp = $decisions->foldLeft(
            $decisions->first()->timestamp,
            static fn (BacktestAnalysisTimestamp $last, BacktestAnalysisDecision $decision): BacktestAnalysisTimestamp => $decision->timestamp->isAfterOrEqual($last)
                ? $decision->timestamp
                : $last,
        );

        return $allOrders->filter(
            static function (BacktestAnalysisOrder $order) use ($firstTimestamp, $lastTimestamp): bool {
                if ($order->openDate->isAfterOrEqual($firstTimestamp)
                    && $lastTimestamp->isAfterOrEqual($order->openDate)) {
                    return true;
                }

                foreach ($order->exitFills as $fill) {
                    if ($fill->date->isAfterOrEqual($firstTimestamp)
                        && $lastTimestamp->isAfterOrEqual($fill->date)) {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * @param  Vec<BacktestAnalysisOrder>  $allOrders
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     * @param  Vec<BacktestAnalysisOrder>  $orders
     * @return Pair<BacktestClusterSummary, BacktestCapitalChart>
     */
    private function summarizeCluster(
        float $initialCapital,
        Vec $allOrders,
        Vec $decisions,
        Vec $orders,
        Option $clusterCount,
    ): Pair {
        $executedOrders = $orders->filter(
            static fn (BacktestAnalysisOrder $order): bool => ! $order->portfolioEntryRejected,
        );
        $closedOrders = $executedOrders->filter(
            static fn (BacktestAnalysisOrder $order): bool => $order->closeDate->isSome(),
        );
        $winningOrders = $closedOrders->filter(
            static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->exists(static fn (float $pnl): bool => $pnl > 0),
        );
        $losingOrders = $closedOrders->filter(
            static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->exists(static fn (float $pnl): bool => $pnl < 0),
        );
        $capitalStart = $this->clusterStartingCapital($initialCapital, $allOrders, $decisions, $clusterCount);
        $movements = Vec::empty();
        $sequence = 0;
        foreach ($orders as $order) {
            foreach ($order->exitFills as $fill) {
                if ($fill->capitalAfter->isSome()) {
                    $movements = $movements->append(new BacktestCapitalMovement(
                        $fill->date,
                        $fill->capitalAfter->getOrElse(0.0),
                        $sequence++,
                    ));
                }
            }
            if ($order->exitFills->isEmpty() && $order->closeDate->isSome()) {
                $movements = $movements->append(new BacktestCapitalMovement(
                    $order->closeDate->getOrElseThrow(new \LogicException('A closing date was expected.')),
                    $order->capitalAfterClose->getOrElse(0.0),
                    $sequence++,
                ));
            }
        }
        $sortedMovements = SortedSet::fromIterable(
            $movements,
            static fn (BacktestCapitalMovement $left, BacktestCapitalMovement $right): int => ($left->date->instant <=> $right->date->instant)
                ?: ($left->sequence <=> $right->sequence),
        );
        $capitalSeries = Vec::of($capitalStart)->concat(
            Vec::fromIterable($sortedMovements)->map(static fn (BacktestCapitalMovement $movement): float => $movement->capital),
        );

        $capitalMinimum = $capitalSeries->reduce(static fn (float $left, float $right): float => min($left, $right));
        $capitalMaximum = $capitalSeries->reduce(static fn (float $left, float $right): float => max($left, $right));
        $chartCoordinates = $this->capitalChartCoordinates($capitalSeries, $capitalMinimum, $capitalMaximum);
        $capitalEnd = $capitalSeries->last();

        return Pair::of(
            new BacktestClusterSummary(
                orders: $executedOrders->count(),
                closed: $closedOrders->count(),
                wins: $winningOrders->count(),
                losses: $losingOrders->count(),
                netProfitLoss: $executedOrders->foldLeft(
                    0.0,
                    static fn (float $total, BacktestAnalysisOrder $order): float => $total
                        + $order->realizedProfitLoss->getOrElse($order->profitLoss->getOrElse(0.0)),
                ),
                winRate: $closedOrders->isNotEmpty() ? $winningOrders->count() / $closedOrders->count() * 100 : 0,
                capitalStart: $capitalStart,
                capitalEnd: $capitalEnd,
            ),
            new BacktestCapitalChart(
                points: implode(' ', $chartCoordinates->toArray()),
                minimum: $capitalMinimum,
                maximum: $capitalMaximum,
                start: $capitalStart,
                end: $capitalEnd,
            ),
        );
    }

    /**
     * @param  Vec<BacktestAnalysisOrder>  $orders
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     */
    private function clusterStartingCapital(float $initialCapital, Vec $orders, Vec $decisions, Option $clusterCount): float
    {
        $capitalStart = $initialCapital;
        if ($clusterCount->isNone() || $decisions->isEmpty()) {
            return $capitalStart;
        }

        $clusterStartDate = $decisions->first()->timestamp;
        $priorExitEvents = Vec::empty();
        $sequence = 0;
        foreach ($orders as $order) {
            if ($order->exitFills->isNotEmpty()) {
                foreach ($order->exitFills as $fill) {
                    if ($fill->date->isBefore($clusterStartDate) && $fill->capitalAfter->isSome()) {
                        $priorExitEvents = $priorExitEvents->append(new BacktestCapitalMovement(
                            $fill->date,
                            $fill->capitalAfter->getOrElse(0.0),
                            $sequence++,
                        ));
                    }
                }
            } elseif ($order->closeDate->exists(
                static fn (BacktestAnalysisTimestamp $date): bool => $date->isBefore($clusterStartDate),
            )) {
                $priorExitEvents = $priorExitEvents->append(new BacktestCapitalMovement(
                    $order->closeDate->getOrElseThrow(new \LogicException('A closing date was expected.')),
                    $order->capitalAfterClose->getOrElse(0.0),
                    $sequence++,
                ));
            }
        }
        if ($priorExitEvents->isNotEmpty()) {
            $latestPriorExit = SortedSet::fromIterable(
                $priorExitEvents,
                static fn (BacktestCapitalMovement $left, BacktestCapitalMovement $right): int => ($left->date->instant <=> $right->date->instant)
                    ?: ($left->sequence <=> $right->sequence),
            )->last();
            $capitalStart = $latestPriorExit->capital;
        }

        return $capitalStart;
    }

    /**
     * @param  Vec<float>  $capitalSeries
     * @return Vec<string>
     */
    private function capitalChartCoordinates(Vec $capitalSeries, float $minimum, float $maximum): Vec
    {
        $capitalRange = max($maximum - $minimum, 1.0);
        $lastChartIndex = max($capitalSeries->count() - 1, 1);
        $coordinates = Vec::empty();
        foreach ($capitalSeries as $index => $point) {
            $x = 24 + 592 * $index / $lastChartIndex;
            $y = 12 + 150 * ($maximum - $point) / $capitalRange;
            $coordinates = $coordinates->append(number_format($x, 2, '.', '').','.number_format($y, 2, '.', ''));
        }
        if ($coordinates->count() === 1) {
            $firstCoordinate = $coordinates->first();
            $coordinates = $coordinates->append('616.00,'.substr($firstCoordinate, strpos($firstCoordinate, ',') + 1));
        }

        return $coordinates;
    }
}
