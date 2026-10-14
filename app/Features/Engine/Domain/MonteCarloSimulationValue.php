<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

final readonly class MonteCarloSimulationValue
{
    public function __construct(
        public int $sequence,
        public float $value,
    ) {}
}
