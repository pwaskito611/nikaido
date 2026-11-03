<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\MarketTypeBacktests;

use Exact\Type\Option\Option;
use Gauss\Number\Number;
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
        public float $grossProfitLoss = 0.0,
        public array $tradeCosts = [],
        public ?float $requestedQuantity = null,
        public ?float $remainingQuantity = null,
        public ?float $fillRatio = null,
        public ?string $status = null,
    ) {}

    /** @param array<string, mixed> $fill */
    public static function fromPersistedArray(array $fill): self
    {
        $tradeCosts = self::costs($fill['trade_costs'] ?? []);
        $profitLoss = self::number($fill['profit_loss'] ?? null);

        return new self(
            quantity: self::number($fill['quantity'] ?? null),
            price: self::number($fill['price'] ?? null),
            date: self::string($fill['date'] ?? null, 'date'),
            source: self::string($fill['source'] ?? null, 'source'),
            reason: self::string($fill['reason'] ?? null, 'reason'),
            profitLoss: $profitLoss,
            capitalAfter: self::number($fill['capital_after'] ?? null),
            grossProfitLoss: self::number($fill['gross_profit_loss'] ?? null)
                ->getOrElse((float) Number::of($profitLoss->getOrElse(0.0))->add(self::sumCosts($tradeCosts))->value()),
            tradeCosts: $tradeCosts,
            requestedQuantity: self::number($fill['requested_quantity'] ?? null)->getOrNull(),
            remainingQuantity: self::number($fill['remaining_quantity'] ?? null)->getOrNull(),
            fillRatio: self::number($fill['fill_ratio'] ?? null)->getOrNull(),
            status: self::string($fill['status'] ?? null, 'status')->getOrNull(),
        );
    }

    /** @param list<string> $perOrderCostComponents */
    public function scaled(float $factor, array $perOrderCostComponents = []): self
    {
        $tradeCosts = $this->tradeCosts;
        foreach ($tradeCosts as $component => $amount) {
            if (! in_array($component, $perOrderCostComponents, true)) {
                $tradeCosts[$component] = $amount * $factor;
            }
        }
        $grossProfitLoss = $this->grossProfitLoss * $factor;

        return new self(
            quantity: $this->quantity->map(static fn (float $value): float => $value * $factor),
            price: $this->price,
            date: $this->date,
            source: $this->source,
            reason: $this->reason,
            profitLoss: $this->profitLoss->map(
                fn (float $_): float => (float) Number::of($grossProfitLoss)->sub(self::sumCosts($tradeCosts))->value(),
            ),
            capitalAfter: $this->capitalAfter,
            grossProfitLoss: $grossProfitLoss,
            tradeCosts: $tradeCosts,
            requestedQuantity: $this->requestedQuantity === null ? null : $this->requestedQuantity * $factor,
            remainingQuantity: $this->remainingQuantity === null ? null : $this->remainingQuantity * $factor,
            fillRatio: $this->fillRatio,
            status: $this->status,
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
            $this->grossProfitLoss,
            $this->tradeCosts,
            $this->requestedQuantity,
            $this->remainingQuantity,
            $this->fillRatio,
            $this->status,
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
        if ($this->requestedQuantity !== null) {
            $fill['requested_quantity'] = $this->requestedQuantity;
        }
        if ($this->remainingQuantity !== null) {
            $fill['remaining_quantity'] = $this->remainingQuantity;
        }
        if ($this->fillRatio !== null) {
            $fill['fill_ratio'] = $this->fillRatio;
        }
        if ($this->status !== null) {
            $fill['status'] = $this->status;
        }
        if ($this->capitalAfter->isSome()) {
            $fill['capital_after'] = $this->capitalAfter->getOrElse(0.0);
        }
        if ($this->tradeCosts !== []) {
            $fill['gross_profit_loss'] = $this->grossProfitLoss;
            $fill['trade_costs'] = $this->tradeCosts;
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

    /** @return array<string, float> */
    private static function costs(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UnexpectedValueException('Portfolio exit-fill trade costs must be a record.');
        }
        foreach ($value as $component => $amount) {
            if (! is_string($component) || ! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount < 0) {
                throw new UnexpectedValueException('Portfolio exit-fill trade costs must contain finite non-negative amounts.');
            }
            $value[$component] = (float) $amount;
        }

        return $value;
    }

    /** @param array<string, float> $costs */
    private static function sumCosts(array $costs): Number
    {
        return array_reduce(
            $costs,
            static fn (Number $total, float $cost): Number => $total->add($cost),
            Number::of(0),
        );
    }
}
