<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\MarketTypeBacktests;

use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class PortfolioExitFill
{
    /**
     * @param  Option<float>  $quantity
     * @param  Option<float>  $price
     * @param  Option<string>  $date
     * @param  Option<string>  $source
     * @param  Option<string>  $reason
     * @param  Option<float>  $profitLoss
     * @param  Option<float>  $capitalAfter
     */
    private function __construct(
        public Option $quantity,
        public Option $price,
        public Option $date,
        public Option $source,
        public Option $reason,
        public Option $profitLoss,
        public Option $capitalAfter,
    ) {}

    /** @param array<string, mixed> $fill */
    public static function fromPersistedArray(array $fill): self
    {
        return new self(
            quantity: self::number($fill['quantity'] ?? null),
            price: self::number($fill['price'] ?? null),
            date: self::string($fill['date'] ?? null, 'date'),
            source: self::string($fill['source'] ?? null, 'source'),
            reason: self::string($fill['reason'] ?? null, 'reason'),
            profitLoss: self::number($fill['profit_loss'] ?? null),
            capitalAfter: self::number($fill['capital_after'] ?? null),
        );
    }

    public function scaled(float $factor): self
    {
        return new self(
            quantity: $this->quantity->map(static fn (float $value): float => $value * $factor),
            price: $this->price,
            date: $this->date,
            source: $this->source,
            reason: $this->reason,
            profitLoss: $this->profitLoss->map(static fn (float $value): float => $value * $factor),
            capitalAfter: $this->capitalAfter,
        );
    }

    public function withCapitalAfter(float $capital): self
    {
        return new self(
            $this->quantity,
            $this->price,
            $this->date,
            $this->source,
            $this->reason,
            $this->profitLoss,
            Option::some($capital),
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
        if ($this->profitLoss->isSome()) {
            $fill['profit_loss'] = $this->profitLoss->getOrElse(0.0);
        }
        if ($this->date->isSome()) {
            $fill['date'] = $this->date->getOrElse('');
        }
        if ($this->source->isSome()) {
            $fill['source'] = $this->source->getOrElse('');
        }
        if ($this->reason->isSome()) {
            $fill['reason'] = $this->reason->getOrElse('');
        }
        if ($this->capitalAfter->isSome()) {
            $fill['capital_after'] = $this->capitalAfter->getOrElse(0.0);
        }

        return $fill;
    }

    private static function number(mixed $value): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException('Portfolio exit-fill numeric values must be finite numbers when present.');
        }

        return Option::some((float) $value);
    }

    private static function string(mixed $value, string $field): Option
    {
        if ($value !== null && ! is_string($value)) {
            throw new UnexpectedValueException("Portfolio exit-fill {$field} must be a string when present.");
        }
        if ($field === 'date' && is_string($value) && strtotime($value) === false) {
            throw new UnexpectedValueException('Portfolio exit-fill dates must be valid date strings when present.');
        }

        return Option::from($value);
    }
}
