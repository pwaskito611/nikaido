<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use InvalidArgumentException;

final readonly class MonteCarloEquityMetrics
{
    public function __construct(
        public float $median,
        public float $mean,
        public float $p5,
        public float $p25,
        public float $p75,
        public float $p95,
    ) {
        $values = [$median, $mean, $p5, $p25, $p75, $p95];
        foreach ($values as $value) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Monte Carlo equity metrics must be finite.');
            }
        }

        if ($p5 > $p25 || $p25 > $median || $median > $p75 || $p75 > $p95) {
            throw new InvalidArgumentException('Monte Carlo equity percentiles must be in ascending order.');
        }
    }

    /** @return array{median: float, mean: float, p5: float, p25: float, p75: float, p95: float} */
    public function toArray(): array
    {
        return [
            'median' => $this->median,
            'mean' => $this->mean,
            'p5' => $this->p5,
            'p25' => $this->p25,
            'p75' => $this->p75,
            'p95' => $this->p95,
        ];
    }
}
