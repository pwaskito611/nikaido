<?php

declare(strict_types=1);

namespace App\Features\Workbench\Models;

use App\Features\Engine\Domain\FluctuationStressTestResult;
use App\Features\Engine\Domain\MonteCarloSimulationResult;
use App\Features\Workbench\Domain\Backtests\BacktestAnalysisDecision;
use App\Features\Workbench\Domain\Backtests\BacktestAnalysisOrder;
use App\Features\Workbench\Domain\Backtests\BacktestCapitalChart;
use App\Features\Workbench\Domain\Backtests\BacktestClusterSummary;
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
