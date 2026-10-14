<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use InvalidArgumentException;

final readonly class MonteCarloHistogramBin
{
    public function __construct(
        public float $from,
        public float $to,
        public int $count,
    ) {
        if (! is_finite($from) || ! is_finite($to) || $from > $to || $count < 0) {
            throw new InvalidArgumentException('Monte Carlo histogram bins require finite ordered bounds and a non-negative count.');
        }
    }

    /** @return array{from: float, to: float, count: int} */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'count' => $this->count,
        ];
    }
}
