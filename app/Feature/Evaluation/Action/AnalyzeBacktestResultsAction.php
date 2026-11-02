<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Action;

use App\Feature\Evaluation\Config\MonteCarloSimulationConfig;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisOptions;
use App\Feature\Evaluation\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Evaluation\Data\BacktestResultsAnalysis;
use App\Feature\Evaluation\Data\FluctuationStressTestResult;
use App\Feature\Evaluation\Data\MonteCarloSimulationResult;
use App\Feature\Evaluation\Data\MonteCarloTrade;
use App\Feature\Evaluation\Process\BacktestClusterAnalysisProcess;
use App\Feature\Evaluation\Process\FluctuationStressTestProcess;
use App\Feature\Evaluation\Process\MonteCarloSimulationProcess;
use Exact\Collection\Vec;
use Exact\Query\Query;
use Illuminate\Validation\ValidationException;

class AnalyzeBacktestResultsAction
{
    public function __construct(
        private readonly FluctuationStressTestProcess $runStressTest,
        private readonly MonteCarloSimulationProcess $runMonteCarlo,
        private readonly BacktestClusterAnalysisProcess $analyzeCluster,
    ) {}

    public function execute(
        BacktestAnalysisInput $input,
        BacktestAnalysisOptions $options,
    ): BacktestResultsAnalysis {
        $stressTest = $options->stressParameter->map(
            fn (float $parameter): FluctuationStressTestResult => $this->runStressTest
                ->execute(
                    $input->stressTestInput->getOrElseThrow(
                        new \LogicException('Stress-test input must be parsed when a stress parameter is provided.'),
                    ),
                    $parameter,
                ),
        );
        $monteCarlo = $options->monteCarloConfig->map(
            fn (MonteCarloSimulationConfig $config): MonteCarloSimulationResult => $this->runMonteCarloAnalysis($input->orders, $config),
        );
        $cluster = $this->analyzeCluster->execute($input, $options);
        $performanceMetrics = $this->performanceMetrics($input);

        return new BacktestResultsAnalysis(
            decisions: $cluster->decisions,
            orders: $cluster->orders,
            clusterCount: $options->clusterCount,
            selectedCluster: $options->clusterCount->map(
                static fn (int $_): int => $options->selectedCluster,
            ),
            clusterAnalysis: $cluster->summary,
            capitalChart: $cluster->capitalChart,
            profitFactor: $performanceMetrics['profitFactor'],
            closedTradeCount: $performanceMetrics['closedTradeCount'],
            averageDrawdownPercent: $performanceMetrics['averageDrawdownPercent'],
            maximumDrawdownPercent: $performanceMetrics['maximumDrawdownPercent'],
            medianDrawdownPercent: $performanceMetrics['medianDrawdownPercent'],
            stressTest: $stressTest,
            monteCarlo: $monteCarlo,
        );
    }

    /**
     * Drawdown statistics use realized equity after each exit fill and include
     * only observations below the running all-time high for average and median.
     *
     * @return array{
     *     profitFactor: ?float,
     *     closedTradeCount: int,
     *     averageDrawdownPercent: float,
     *     maximumDrawdownPercent: float,
     *     medianDrawdownPercent: float
     * }
     */
    private function performanceMetrics(BacktestAnalysisInput $input): array
    {
        $eligibleOrders = Query::from($input->orders)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => ! $order->portfolioEntryRejected);
        $closedTrades = $eligibleOrders
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->closeDate->isSome()
                && $order->profitLoss->isSome())
            ->execute();
        $closedTradeCount = $closedTrades->count();
        $grossProfit = Query::from($closedTrades)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->getOrElse(0.0) > 0)
            ->sum(static fn (BacktestAnalysisOrder $order): float => $order->profitLoss->getOrElse(0.0));
        $grossLoss = Query::from($closedTrades)
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->getOrElse(0.0) < 0)
            ->sum(static fn (BacktestAnalysisOrder $order): float => abs($order->profitLoss->getOrElse(0.0)));

        $indexedOrders = $input->orders->enumerate();
        $exitFillMovements = Query::from($indexedOrders)
            ->filter(static fn (array $indexedOrder): bool => ! $indexedOrder[1]->portfolioEntryRejected
                && $indexedOrder[1]->exitFills->isNotEmpty())
            ->flatMap(static function (array $indexedOrder): Vec {
                [$orderIndex, $order] = $indexedOrder;

                return Query::from($order->exitFills->enumerate())
                    ->filter(static fn (array $indexedFill): bool => $indexedFill[1]->capitalAfter->isSome())
                    ->select(static fn (array $indexedFill): array => [
                        'date' => $indexedFill[1]->date->instant,
                        'orderIndex' => $orderIndex,
                        'fillIndex' => $indexedFill[0],
                        'capital' => $indexedFill[1]->capitalAfter->getOrElse(0.0),
                    ])
                    ->execute();
            })
            ->execute();
        $closedOrderMovements = Query::from($indexedOrders)
            ->filter(static fn (array $indexedOrder): bool => ! $indexedOrder[1]->portfolioEntryRejected
                && $indexedOrder[1]->exitFills->isEmpty()
                && $indexedOrder[1]->closeDate->isSome()
                && $indexedOrder[1]->capitalAfterClose->isSome())
            ->select(static fn (array $indexedOrder): array => [
                'date' => $indexedOrder[1]->closeDate
                    ->getOrElseThrow(new \LogicException('A closed order must have a closing date.'))
                    ->instant,
                'orderIndex' => $indexedOrder[0],
                'fillIndex' => 0,
                'capital' => $indexedOrder[1]->capitalAfterClose->getOrElse(0.0),
            ])
            ->execute();
        $equityMovements = Query::from($exitFillMovements)
            ->unionAll($closedOrderMovements)
            ->orderBy(static fn (array $movement) => $movement['date'])
            ->thenBy(static fn (array $movement): int => $movement['orderIndex'])
            ->thenBy(static fn (array $movement): int => $movement['fillIndex'])
            ->execute();
        $drawdownState = Query::from($equityMovements)->fold(
            [
                'peakCapital' => $input->initialCapital,
                'drawdowns' => Vec::empty(),
                'maximumDrawdownPercent' => 0.0,
            ],
            static function (array $state, array $movement): array {
                $capital = $movement['capital'];
                $peakCapital = $state['peakCapital'];
                if ($capital >= $peakCapital) {
                    return [
                        ...$state,
                        'peakCapital' => $capital,
                    ];
                }
                if ($peakCapital <= 0) {
                    return $state;
                }

                $drawdownPercent = ($peakCapital - $capital) / $peakCapital * 100;

                return [
                    ...$state,
                    'drawdowns' => $state['drawdowns']->append($drawdownPercent),
                    'maximumDrawdownPercent' => max($state['maximumDrawdownPercent'], $drawdownPercent),
                ];
            },
        );
        $sortedDrawdowns = Query::from($drawdownState['drawdowns'])
            ->sortBy(static fn (float $drawdown): float => $drawdown)
            ->execute();
        $drawdownCount = $sortedDrawdowns->count();
        $middle = intdiv($drawdownCount, 2);
        $medianDrawdownPercent = match (true) {
            $drawdownCount === 0 => 0.0,
            $drawdownCount % 2 === 1 => $sortedDrawdowns->at($middle),
            default => ($sortedDrawdowns->at($middle - 1) + $sortedDrawdowns->at($middle)) / 2,
        };
        $averageDrawdownPercent = Query::from($drawdownState['drawdowns'])
            ->average()
            ->getOrElse(0.0);

        return [
            'profitFactor' => match (true) {
                $closedTradeCount === 0 => null,
                $grossLoss > 0 => $grossProfit / $grossLoss,
                $grossProfit > 0 => INF,
                default => null,
            },
            'closedTradeCount' => $closedTradeCount,
            'averageDrawdownPercent' => $averageDrawdownPercent,
            'maximumDrawdownPercent' => $drawdownState['maximumDrawdownPercent'],
            'medianDrawdownPercent' => $medianDrawdownPercent,
        ];
    }

    private function runMonteCarloAnalysis(
        Vec $orders,
        MonteCarloSimulationConfig $config,
    ): MonteCarloSimulationResult {
        $closedTrades = $orders
            ->filter(static fn (BacktestAnalysisOrder $order): bool => $order->closeDate->isSome());
        if ($closedTrades->isEmpty()) {
            throw ValidationException::withMessages([
                'monte_carlo' => 'Monte Carlo requires at least one closed trade with a numeric P/L.',
            ]);
        }
        if ($closedTrades->exists(
            static fn (BacktestAnalysisOrder $order): bool => $order->profitLoss->isNone(),
        )) {
            throw ValidationException::withMessages([
                'monte_carlo' => 'Every closed trade must have a numeric P/L for Monte Carlo simulation.',
            ]);
        }
        if ($config->riskModel === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
            && $closedTrades->exists(
                static function (BacktestAnalysisOrder $order): bool {
                    $capitalAtOpen = $order->capitalAtOpen->getOrNull();

                    return $capitalAtOpen === null || $capitalAtOpen <= 0 || ! is_finite($capitalAtOpen);
                },
            )) {
            throw ValidationException::withMessages([
                'monte_carlo' => 'Compounded return requires a positive opening capital for every closed trade.',
            ]);
        }
        if ($config->method === MonteCarloSimulationConfig::METHOD_SHUFFLE
            && $config->tradesPerSimulation !== null
            && $config->tradesPerSimulation !== $closedTrades->count()) {
            throw ValidationException::withMessages([
                'trades_per_simulation' => 'Shuffle must use every historical closed trade exactly once.',
            ]);
        }

        $tradesPerSimulation = $config->tradesPerSimulation ?? $closedTrades->count();
        if ($config->simulationCount * $tradesPerSimulation > MonteCarloSimulationConfig::MAX_SIMULATED_TRADES) {
            throw ValidationException::withMessages([
                'simulation_count' => 'The requested simulation workload exceeds the supported limit.',
            ]);
        }

        $trades = $closedTrades->map(
            static fn (BacktestAnalysisOrder $order): MonteCarloTrade => new MonteCarloTrade(
                profitLoss: $order->profitLoss->getOrElse(0.0),
                capitalAtOpen: $order->capitalAtOpen,
            ),
        );

        return $this->runMonteCarlo->execute($trades, $config);
    }
}
