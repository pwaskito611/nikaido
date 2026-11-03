<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\MarketTypeBacktests;

use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class PortfolioEntryFill
{
    /**
     * @param  Option<float>  $quantity
     * @param  Option<float>  $price
     * @param  Option<string>  $date
     * @param  Option<string>  $source
     */
    private function __construct(
        public Option $quantity,
        public Option $price,
        public Option $date,
        public Option $source,
        public array $tradeCosts = [],
    ) {}

    /** @param array<string, mixed> $fill */
    public static function fromPersistedArray(array $fill): self
    {
        return new self(
            self::number($fill, 'quantity'),
            self::number($fill, 'price'),
            self::string($fill, 'date'),
            self::string($fill, 'source'),
            self::costs($fill['trade_costs'] ?? []),
        );
    }

    /** @param list<string> $perOrderCostComponents */
    public function scaled(float $factor, array $perOrderCostComponents = []): self
    {
        return new self(
            $this->quantity->map(static fn (float $quantity): float => $quantity * $factor),
            $this->price,
            $this->date,
            $this->source,
            self::scaleCosts($this->tradeCosts, $factor, $perOrderCostComponents),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $fill = [];
        if ($this->quantity->isSome()) {
            $fill['quantity'] = $this->quantity->getOrElse(0.0);
        }
        if ($this->price->isSome()) {
            $fill['price'] = $this->price->getOrElse(0.0);
        }
        if ($this->date->isSome()) {
            $fill['date'] = $this->date->getOrElse('');
        }
        if ($this->source->isSome()) {
            $fill['source'] = $this->source->getOrElse('');
        }
        if ($this->tradeCosts !== []) {
            $fill['trade_costs'] = $this->tradeCosts;
        }

        return $fill;
    }

    /**
     * @param  array<string, float>  $costs
     * @param  list<string>  $perOrderCostComponents
     * @return array<string, float>
     */
    private static function scaleCosts(array $costs, float $factor, array $perOrderCostComponents): array
    {
        foreach ($costs as $component => $amount) {
            if (! in_array($component, $perOrderCostComponents, true)) {
                $costs[$component] = $amount * $factor;
            }
        }

        return $costs;
    }

    /** @param array<string, mixed> $fill */
    private static function number(array $fill, string $field): Option
    {
        $value = $fill[$field] ?? null;
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Portfolio entry-fill {$field} must be finite when present.");
        }

        return Option::some((float) $value);
    }

    /** @param array<string, mixed> $fill */
    private static function string(array $fill, string $field): Option
    {
        $value = $fill[$field] ?? null;
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value) || ($field === 'date' && strtotime($value) === false)) {
            throw new UnexpectedValueException("Portfolio entry-fill {$field} must be a valid string when present.");
        }

        return Option::some($value);
    }

    /** @return array<string, float> */
    private static function costs(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UnexpectedValueException('Portfolio entry-fill trade costs must be a record.');
        }
        foreach ($value as $component => $amount) {
            if (! is_string($component) || ! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount < 0) {
                throw new UnexpectedValueException('Portfolio entry-fill trade costs must contain finite non-negative amounts.');
            }
            $value[$component] = (float) $amount;
        }

        return $value;
    }
}
