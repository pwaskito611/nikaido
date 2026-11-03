<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\MarketTypeBacktests;

use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class PortfolioOrderExtreme
{
    /**
     * @param  Option<float>  $distance
     * @param  Option<string>  $date
     * @param  Option<string>  $source
     */
    private function __construct(
        public float $price,
        public Option $date,
        public Option $source,
        public Option $distance,
    ) {}

    public static function fromPersistedValue(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_array($value) || ! is_numeric($value['price'] ?? null) || ! is_finite((float) $value['price'])) {
            throw new UnexpectedValueException("Portfolio order {$field} must contain a finite price.");
        }

        $date = self::optionalString($value['date'] ?? null, "{$field}.date");
        $source = self::optionalString($value['source'] ?? null, "{$field}.source");
        $distance = self::optionalNumber($value['distance'] ?? null, "{$field}.distance");

        return Option::some(new self((float) $value['price'], $date, $source, $distance));
    }

    /** @return array{price: float, date?: string|null, source?: string|null, distance?: float} */
    public function toArray(): array
    {
        $value = ['price' => $this->price];
        if ($this->date->isSome()) {
            $value['date'] = $this->date->getOrNull();
        }
        if ($this->source->isSome()) {
            $value['source'] = $this->source->getOrNull();
        }
        if ($this->distance->isSome()) {
            $value['distance'] = $this->distance->getOrNull();
        }

        return $value;
    }

    private static function optionalString(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be a string when present.");
        }

        return Option::some($value);
    }

    private static function optionalNumber(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be finite when present.");
        }

        return Option::some((float) $value);
    }
}
