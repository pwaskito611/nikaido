<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Type\Option\Option;
use InvalidArgumentException;

final readonly class BacktestPriceObservation
{
    /**
     * @param  Option<float>  $distance
     */
    public function __construct(
        public float $price,
        public StressTestTimestamp $date,
        public StressTestPriceSource $source,
        public Option $distance,
    ) {
        if (! is_finite($price)) {
            throw new InvalidArgumentException('Backtest observation price must be finite.');
        }
        if ($distance->exists(static fn (float $value): bool => ! is_finite($value) || $value < 0)) {
            throw new InvalidArgumentException('Backtest observation distance must be finite and non-negative.');
        }
    }

    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'date' => $this->date->toPersistedValue(),
            'source' => $this->source->value,
            ...($this->distance->isSome()
                ? ['distance' => $this->distance->getOrElse(0.0)]
                : []),
        ];
    }
}
