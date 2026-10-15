<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Model;

use App\Feature\Backtest\Data\Analysis\BacktestAnalysisDecision;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Backtest\Data\Analysis\BacktestCapitalChart;
use App\Feature\Backtest\Data\Analysis\BacktestClusterSummary;
use App\Feature\Backtest\Data\FluctuationStressTestResult;
use App\Feature\Backtest\Data\MonteCarloSimulationResult;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;

final readonly class BacktestResultsAnalysis
{
    /**
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     * @param  Vec<BacktestAnalysisOrder>  $orders
     * @param  Option<int>  $clusterCount
     * @param  Option<int>  $selectedCluster
     * @param  Option<FluctuationStressTestResult>  $stressTest
     * @param  Option<MonteCarloSimulationResult>  $monteCarlo
     */
    public function __construct(
        public Vec $decisions,
        public Vec $orders,
        public Option $clusterCount,
        public Option $selectedCluster,
        public BacktestClusterSummary $clusterAnalysis,
        public BacktestCapitalChart $capitalChart,
        public Option $stressTest,
        public Option $monteCarlo,
    ) {}
}
