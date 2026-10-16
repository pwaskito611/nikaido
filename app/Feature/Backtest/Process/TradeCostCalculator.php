<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process;

use App\Feature\Backtest\Config\TradeCostComponentConfig;
use InvalidArgumentException;

final class TradeCostCalculator
{
    public function transaction(
        TradeCostComponentConfig $component,
        float $quantity,
        float $transactionValue,
        bool $chargePerOrder,
    ): float {
        if (! $component->enabled || ! $chargePerOrder && $component->method === 'per_order') {
            return 0.0;
        }

        $cost = match ($component->method) {
            'per_order' => $component->rate,
            'transaction_value_percent' => $transactionValue * $component->rate / 100,
            'per_quantity' => $quantity * $component->rate,
            default => throw new InvalidArgumentException("Unsupported transaction cost method: {$component->method}."),
        };

        return $this->finiteCost($cost);
    }

    public function holding(
        TradeCostComponentConfig $component,
        float $entryNotionalDays,
    ): float {
        if (! $component->enabled) {
            return 0.0;
        }
        if ($component->method !== 'daily_notional_percent') {
            throw new InvalidArgumentException("Unsupported holding cost method: {$component->method}.");
        }

        return $this->finiteCost($entryNotionalDays * $component->rate / 100);
    }

    private function finiteCost(float $cost): float
    {
        if (! is_finite($cost) || $cost < 0) {
            throw new InvalidArgumentException('Calculated trade cost must be finite and non-negative.');
        }

        return $cost;
    }
}
