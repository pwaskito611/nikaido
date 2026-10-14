<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use InvalidArgumentException;

final readonly class MonteCarloDrawdownMetrics
{
    public function __construct(
        public float $median,
        public float $p95,
        public float $worst,
    ) {
        if (! is_finite($median) || ! is_finite($p95) || ! is_finite($worst)
            || $median < 0 || $p95 < $median || $worst < $p95) {
            throw new InvalidArgumentException('Monte Carlo drawdown metrics must be finite, non-negative, and ordered.');
        }
    }

    /** @return array{median: float, p95: float, worst: float} */
    public function toArray(): array
    {
        return [
            'median' => $this->median,
            'p95' => $this->p95,
            'worst' => $this->worst,
        ];
    }
}
