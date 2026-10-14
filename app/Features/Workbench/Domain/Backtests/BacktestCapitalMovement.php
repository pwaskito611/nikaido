<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

final readonly class BacktestCapitalMovement
{
    public function __construct(
        public BacktestAnalysisTimestamp $date,
        public float $capital,
        public int $sequence,
    ) {}
}
