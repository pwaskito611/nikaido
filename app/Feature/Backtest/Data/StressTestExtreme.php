<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class StressTestExtreme
{
    /**
     * @param  Option<StressTestTimestamp>  $date
     * @param  Option<StressTestPriceSource>  $source
     */
    private function __construct(
        public float $price,
        public Option $date,
        public Option $source,
    ) {}

    /** @return Option<self> */
    public static function fromPersistedValue(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_array($value)
            || ! is_numeric($value['price'] ?? null)
            || ! is_finite((float) $value['price'])) {
            throw new UnexpectedValueException("Stress-test order {$field} must contain a finite price.");
        }

        return Option::some(new self(
            price: (float) $value['price'],
            date: StressTestTimestamp::fromPersistedValue($value['date'] ?? null, "{$field}.date"),
            source: StressTestPriceSource::fromPersistedValue($value['source'] ?? null, "{$field}.source"),
        ));
    }

    /** @return array{price: float, date: ?string, source: ?string} */
    public function toArray(): array
    {
        return [
            'price' => $this->price,
            'date' => $this->date->map(
                static fn (StressTestTimestamp $date): string => $date->toPersistedValue(),
            )->getOrNull(),
            'source' => $this->source->map(
                static fn (StressTestPriceSource $source): string => $source->value,
            )->getOrNull(),
        ];
    }
}
