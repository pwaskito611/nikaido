<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

final readonly class BacktestCapitalMovement
{
    public function __construct(
        public BacktestAnalysisTimestamp $date,
        public float $capital,
        public int $sequence,
    ) {}
}
