<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOptions;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Backtest\Data\FluctuationStressTestResult;
use App\Feature\Backtest\Data\MonteCarloSimulationResult;
use App\Feature\Backtest\Data\MonteCarloTrade;
use App\Feature\Backtest\Model\BacktestResultsAnalysis;
use App\Feature\Backtest\Process\BacktestClusterAnalysisProcess;
use App\Feature\Backtest\Process\FluctuationStressTestProcess;
use App\Feature\Backtest\Process\MonteCarloSimulationProcess;
use Exact\Collection\Vec;
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

        return new BacktestResultsAnalysis(
            decisions: $cluster->decisions,
            orders: $cluster->orders,
            clusterCount: $options->clusterCount,
            selectedCluster: $options->clusterCount->map(
                static fn (int $_): int => $options->selectedCluster,
            ),
            clusterAnalysis: $cluster->summary,
            capitalChart: $cluster->capitalChart,
            stressTest: $stressTest,
            monteCarlo: $monteCarlo,
        );
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
