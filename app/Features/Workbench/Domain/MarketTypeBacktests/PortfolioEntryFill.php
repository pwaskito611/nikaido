<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\MarketTypeBacktests;

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
    ) {}

    /** @param array<string, mixed> $fill */
    public static function fromPersistedArray(array $fill): self
    {
        return new self(
            self::number($fill, 'quantity'),
            self::number($fill, 'price'),
            self::string($fill, 'date'),
            self::string($fill, 'source'),
        );
    }

    public function scaled(float $factor): self
    {
        return new self(
            $this->quantity->map(static fn (float $quantity): float => $quantity * $factor),
            $this->price,
            $this->date,
            $this->source,
        );
    }

    /** @return array<string, float|string> */
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

        return $fill;
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
}
