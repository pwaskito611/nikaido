<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Data\Analysis;

final readonly class BacktestCapitalMovement
{
    public function __construct(
        public BacktestAnalysisTimestamp $date,
        public float $capital,
        public int $sequence,
    ) {}
}
