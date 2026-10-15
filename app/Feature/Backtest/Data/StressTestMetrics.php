<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Collection\Vec;
use InvalidArgumentException;

final readonly class StressTestMetrics
{
    /** @param Vec<StressTestMetric> $values */
    public function __construct(public Vec $values)
    {
        $seen = Vec::empty();
        foreach ($values as $metric) {
            if (! $metric instanceof StressTestMetric) {
                throw new InvalidArgumentException('Stress-test metrics must contain typed metric values.');
            }
            if ($seen->contains($metric->name)) {
                throw new InvalidArgumentException("Stress-test metric {$metric->name->value} is duplicated.");
            }
            $seen = $seen->append($metric->name);
        }

        if ($seen->count() !== count(StressTestMetricName::cases())) {
            throw new InvalidArgumentException('Stress-test results must contain every defined metric exactly once.');
        }
    }

    /** @return array<string, int|float|null> */
    public function toArray(): array
    {
        $metrics = [];
        foreach ($this->values as $metric) {
            $metrics[$metric->name->value] = $metric->value->getOrNull();
        }

        return $metrics;
    }
}
