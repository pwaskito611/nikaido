<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

use InvalidArgumentException;

final readonly class TradeCostComponentConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $method = 'transaction_value_percent',
        public float $rate = 0.0,
        public string $appliesTo = 'both',
    ) {
        if (! in_array($method, ['per_order', 'transaction_value_percent', 'per_quantity', 'daily_notional_percent'], true)) {
            throw new InvalidArgumentException("Unsupported trade-cost method: {$method}.");
        }
        if (! is_finite($rate) || $rate < 0) {
            throw new InvalidArgumentException('Trade-cost rates must be finite and non-negative.');
        }
        if (! in_array($appliesTo, ['both', 'entry', 'exit'], true)) {
            throw new InvalidArgumentException('Trade costs must apply to both sides, entry, or exit.');
        }
    }

    /** @return array{enabled: bool, method: string, rate: float, applies_to: string} */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'method' => $this->method,
            'rate' => $this->rate,
            'applies_to' => $this->appliesTo,
        ];
    }
}
