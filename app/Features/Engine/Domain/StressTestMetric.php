<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use Exact\Type\Option\Option;
use InvalidArgumentException;

final readonly class StressTestMetric
{
    /** @param Option<int|float> $value */
    public function __construct(
        public StressTestMetricName $name,
        public Option $value,
    ) {
        if ($value->exists(
            static fn (mixed $number): bool => (! is_int($number) && ! is_float($number))
                || (is_float($number) && ! is_finite($number)),
        )) {
            throw new InvalidArgumentException('Stress-test metric values must be finite numbers when present.');
        }
    }
}
