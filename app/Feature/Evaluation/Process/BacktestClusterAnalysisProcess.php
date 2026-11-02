<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Process;

use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisDecision;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisOptions;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisTimestamp;
use App\Feature\Evaluation\Data\Analysis\BacktestCapitalChart;
use App\Feature\Evaluation\Data\Analysis\BacktestCapitalMovement;
use App\Feature\Evaluation\Data\Analysis\BacktestClusterAnalysisResult;
use App\Feature\Evaluation\Data\Analysis\BacktestClusterSummary;
use Exact\Collection\Vec;
use Exact\Query\Query;
use Exact\Type\Option\Option;
use Exact\Type\Tuple\Pair;

final class BacktestClusterAnalysisProcess
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

        $firstTimestamp = Query::from($decisions)
            ->minBy(static fn (BacktestAnalysisDecision $decision): \DateTimeImmutable => $decision->timestamp->instant)
            ->map(static fn (BacktestAnalysisDecision $decision): BacktestAnalysisTimestamp => $decision->timestamp)
            ->getOrElseThrow(new \LogicException('A first decision was expected.'));
        $lastTimestamp = Query::from($decisions)
            ->maxBy(static fn (BacktestAnalysisDecision $decision): \DateTimeImmutable => $decision->timestamp->instant)
            ->map(static fn (BacktestAnalysisDecision $decision): BacktestAnalysisTimestamp => $decision->timestamp)
            ->getOrElseThrow(new \LogicException('A last decision was expected.'));

        return Query::from($allOrders)
            ->filter(
                static fn (BacktestAnalysisOrder $order): bool => (
                    $order->openDate->isAfterOrEqual($firstTimestamp)
                    && $lastTimestamp->isAfterOrEqual($order->openDate)
                ) || $order->exitFills->exists(
                    static fn ($fill): bool => $fill->date->isAfterOrEqual($firstTimestamp)
                        && $lastTimestamp->isAfterOrEqual($fill->date),
                ),
            )
            ->execute();
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
        $executedOrders = Query::from($orders)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => ! $order->portfolioEntryRejected)
            ->execute();
        $closedOrders = Query::from($executedOrders)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->closeDate->isSome())
            ->execute();
        $winningOrders = Query::from($closedOrders)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->exists(static fn (float $pnl): bool => $pnl > 0))
            ->execute();
        $losingOrders = Query::from($closedOrders)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->exists(static fn (float $pnl): bool => $pnl < 0))
            ->execute();
        $capitalStart = $this->clusterStartingCapital($initialCapital, $allOrders, $decisions, $clusterCount);
        $movements = Query::from($this->capitalMovements($orders))
            ->orderBy(static fn (BacktestCapitalMovement $movement): \DateTimeImmutable => $movement->date->instant)
            ->thenBy(static fn (BacktestCapitalMovement $movement): int => $movement->sequence)
            ->execute();
        $capitalSeries = Vec::of($capitalStart)->concat(
            $movements->map(static fn (BacktestCapitalMovement $movement): float => $movement->capital),
        );

        $capitalMinimum = $capitalSeries->reduce(static fn (float $left, float $right): float => min($left, $right));
        $capitalMaximum = $capitalSeries->reduce(static fn (float $left, float $right): float => max($left, $right));
        $chartCoordinates = $this->capitalChartCoordinates($capitalSeries, $capitalMinimum, $capitalMaximum);
        $capitalEnd = $capitalSeries->last();

        $netProfitLoss = Query::from($executedOrders)->sum(
            static fn (BacktestAnalysisOrder $order): float => $order->realizedProfitLoss->getOrElse(
                $order->profitLoss->getOrElse(0.0),
            ),
        );

        return Pair::of(
            new BacktestClusterSummary(
                orders: $executedOrders->count(),
                closed: $closedOrders->count(),
                wins: $winningOrders->count(),
                losses: $losingOrders->count(),
                netProfitLoss: $netProfitLoss,
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
        $priorExitEvents = Query::from($this->capitalMovements($orders))
            ->filter(static fn (BacktestCapitalMovement $movement): bool => $movement->date->isBefore($clusterStartDate))
            ->execute();
        if ($priorExitEvents->isNotEmpty()) {
            $latestPriorExit = Query::from($priorExitEvents)
                ->orderBy(static fn (BacktestCapitalMovement $movement): \DateTimeImmutable => $movement->date->instant)
                ->thenBy(static fn (BacktestCapitalMovement $movement): int => $movement->sequence)
                ->execute()
                ->last();
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
        $coordinates = Query::from($capitalSeries->enumerate())
            ->select(static function (array $indexedPoint) use ($lastChartIndex, $maximum, $capitalRange): string {
                [$index, $point] = $indexedPoint;
                $x = 24 + 592 * $index / $lastChartIndex;
                $y = 12 + 150 * ($maximum - $point) / $capitalRange;

                return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
            })
            ->execute();
        if ($coordinates->count() === 1) {
            $firstCoordinate = $coordinates->first();
            $coordinates = $coordinates->append('616.00,'.substr($firstCoordinate, strpos($firstCoordinate, ',') + 1));
        }

        return $coordinates;
    }

    /**
     * @param  Vec<BacktestAnalysisOrder>  $orders
     * @return Vec<BacktestCapitalMovement>
     */
    private function capitalMovements(Vec $orders): Vec
    {
        return Query::from($orders->enumerate())
            ->flatMap(static function (array $indexedOrder): Vec {
                [$orderIndex, $order] = $indexedOrder;

                return $order->exitFills->isNotEmpty()
                    ? Query::from($order->exitFills->enumerate())
                        ->filter(static fn (array $indexedFill): bool => $indexedFill[1]->capitalAfter->isSome())
                        ->select(static fn (array $indexedFill): BacktestCapitalMovement => new BacktestCapitalMovement(
                            $indexedFill[1]->date,
                            $indexedFill[1]->capitalAfter->getOrElse(0.0),
                            $orderIndex + $indexedFill[0],
                        ))
                        ->execute()
                    : $order->closeDate->map(
                        static fn (BacktestAnalysisTimestamp $date): Vec => Vec::of(new BacktestCapitalMovement(
                            $date,
                            $order->capitalAfterClose->getOrElse(0.0),
                            $orderIndex,
                        )),
                    )->getOrElse(Vec::empty());
            })
            ->execute()
            ->enumerate()
            ->map(static fn (array $indexedMovement): BacktestCapitalMovement => new BacktestCapitalMovement(
                $indexedMovement[1]->date,
                $indexedMovement[1]->capital,
                $indexedMovement[0],
            ));
    }
}
