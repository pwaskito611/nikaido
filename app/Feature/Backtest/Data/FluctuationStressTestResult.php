<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Collection\Vec;

final readonly class FluctuationStressTestResult
{
    /** @param Vec<StressTestScenarioOrder> $orders */
    public function __construct(
        public StressTestMetrics $baseline,
        public StressTestMetrics $scenario,
        public Vec $orders,
        public float $x,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'baseline' => $this->baseline->toArray(),
            'scenario' => $this->scenario->toArray(),
            'orders' => $this->orders
                ->map(static fn (StressTestScenarioOrder $order): array => $order->toArray())
                ->toArray(),
            'x' => $this->x,
        ];
    }
}
