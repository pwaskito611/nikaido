<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

final readonly class MonteCarloSimulationValue
{
    public function __construct(
        public int $sequence,
        public float $value,
    ) {}
}
