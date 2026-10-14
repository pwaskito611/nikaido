<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

final readonly class BacktestCapitalChart
{
    public function __construct(
        public string $points,
        public float $minimum,
        public float $maximum,
        public float $start,
        public float $end,
    ) {}

    /** @return array{points: string, minimum: float, maximum: float, start: float, end: float} */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
