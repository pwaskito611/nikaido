<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

use InvalidArgumentException;

final readonly class TradeCostConfig
{
    private const TRANSACTION_COMPONENTS = [
        'commission',
        'exchange_fee',
        'clearing_fee',
        'regulatory_fee',
        'transaction_tax',
    ];

    private const HOLDING_COMPONENTS = ['borrow_fee', 'financing_fee'];

    /**
     * @param  array<string, TradeCostComponentConfig>  $components
     */
    public function __construct(
        public bool $enabled = false,
        public array $components = [],
    ) {
        foreach ([...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS] as $name) {
            if (array_key_exists($name, $components) && ! $components[$name] instanceof TradeCostComponentConfig) {
                throw new InvalidArgumentException("Trade-cost component {$name} has an invalid configuration.");
            }
            $component = $components[$name] ?? new TradeCostComponentConfig(
                method: in_array($name, self::HOLDING_COMPONENTS, true)
                    ? 'daily_notional_percent'
                    : 'transaction_value_percent',
            );
            $allowedMethods = in_array($name, self::HOLDING_COMPONENTS, true)
                ? ['daily_notional_percent']
                : ['per_order', 'transaction_value_percent', 'per_quantity'];
            if (! in_array($component->method, $allowedMethods, true)) {
                throw new InvalidArgumentException("Trade-cost component {$name} does not support method {$component->method}.");
            }
        }
        foreach (array_keys($components) as $name) {
            if (! in_array($name, [...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS], true)) {
                throw new InvalidArgumentException("Unsupported trade-cost component: {$name}.");
            }
        }
    }

    /** @return array<string, TradeCostComponentConfig> */
    public function components(): array
    {
        $components = [];
        foreach ([...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS] as $name) {
            $components[$name] = $this->components[$name] ?? new TradeCostComponentConfig(
                method: in_array($name, self::HOLDING_COMPONENTS, true)
                    ? 'daily_notional_percent'
                    : 'transaction_value_percent',
            );
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $componentValues = $input['components'] ?? array_diff_key($input, ['enabled' => true]);
        if (! is_array($componentValues)) {
            throw new InvalidArgumentException('Trade-cost components must be a record.');
        }
        foreach (array_keys($componentValues) as $name) {
            if (! in_array($name, [...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS], true)) {
                throw new InvalidArgumentException("Unsupported trade-cost component: {$name}.");
            }
        }
        foreach (array_keys($input) as $key) {
            if (! in_array($key, ['enabled', 'components', ...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS], true)) {
                throw new InvalidArgumentException("Unsupported trade-cost setting: {$key}.");
            }
        }
        if (array_key_exists('components', $input)
            && array_diff(array_keys($input), ['enabled', 'components']) !== []) {
            throw new InvalidArgumentException('Trade-cost components cannot mix normalized and form configuration shapes.');
        }
        $components = [];
        foreach ([...self::TRANSACTION_COMPONENTS, ...self::HOLDING_COMPONENTS] as $name) {
            $values = $componentValues[$name] ?? [];
            if (! is_array($values)) {
                throw new InvalidArgumentException("Trade-cost component {$name} must be a record.");
            }
            foreach (array_keys($values) as $key) {
                if (! in_array($key, ['enabled', 'method', 'rate', 'applies_to'], true)) {
                    throw new InvalidArgumentException("Unsupported setting for trade-cost component {$name}: {$key}.");
                }
            }
            $method = $values['method'] ?? (in_array($name, self::HOLDING_COMPONENTS, true)
                ? 'daily_notional_percent'
                : 'transaction_value_percent');
            $rate = $values['rate'] ?? 0;
            if (! is_string($method) || ! is_numeric($rate) || ! is_finite((float) $rate)) {
                throw new InvalidArgumentException("Trade-cost component {$name} has invalid values.");
            }
            $components[$name] = new TradeCostComponentConfig(
                enabled: self::boolean($values['enabled'] ?? false, "trade-cost component {$name} enabled"),
                method: $method,
                rate: (float) $rate,
                appliesTo: is_string($values['applies_to'] ?? 'both')
                    ? ($values['applies_to'] ?? 'both')
                    : throw new InvalidArgumentException("Trade-cost component {$name} has an invalid side."),
            );
        }

        return new self(
            enabled: self::boolean($input['enabled'] ?? false, 'trade-cost enabled'),
            components: $components,
        );
    }

    private static function boolean(mixed $value, string $field): bool
    {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new InvalidArgumentException("The {$field} value must be boolean.");
        }

        return $parsed;
    }

    /** @return array{enabled: bool, components: array<string, array{enabled: bool, method: string, rate: float, applies_to: string}>} */
    public function toArray(): array
    {
        $components = [];
        foreach ($this->components() as $name => $component) {
            $components[$name] = $component->toArray();
        }

        return ['enabled' => $this->enabled, 'components' => $components];
    }
}
