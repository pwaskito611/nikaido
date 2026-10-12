<?php

declare(strict_types=1);

namespace App\Workbench\Models;

final readonly class BacktestResultsAnalysis
{
    /**
     * @param  list<array<string, mixed>>  $decisions
     * @param  list<array<string, mixed>>  $orders
     * @param  array<string, int|float>  $clusterAnalysis
     * @param  array{points: string, minimum: float, maximum: float, start: float, end: float}  $capitalChart
     */
    public function __construct(
        public array $decisions,
        public array $orders,
        public ?int $clusterCount,
        public ?int $selectedCluster,
        public array $clusterAnalysis,
        public array $capitalChart,
        public ?array $stressTest,
        public ?array $monteCarlo,
    ) {}
}
