<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

use Exact\Collection\Vec;

final readonly class BacktestClusterAnalysisResult
{
    /**
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     * @param  Vec<BacktestAnalysisOrder>  $orders
     */
    public function __construct(
        public Vec $decisions,
        public Vec $orders,
        public BacktestClusterSummary $summary,
        public BacktestCapitalChart $capitalChart,
    ) {}
}
