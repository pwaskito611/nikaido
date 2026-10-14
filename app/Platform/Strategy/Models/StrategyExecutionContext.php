<?php

declare(strict_types=1);

namespace App\Platform\Strategy\Models;

use InvalidArgumentException;

class StrategyExecutionContext
{
    public function __construct(
        public float $currentCapital,
        public readonly string $quantityUnit,
        public readonly float $quantityMultiplier,
    ) {
        if (! is_finite($currentCapital) || $currentCapital <= 0) {
            throw new InvalidArgumentException('Current capital must be a positive finite number.');
        }

        if (! in_array($quantityUnit, ['units', 'contract', 'lot'], true)) {
            throw new InvalidArgumentException("Unsupported market pair quantity unit: {$quantityUnit}.");
        }

        if (! is_finite($quantityMultiplier) || $quantityMultiplier <= 0) {
            throw new InvalidArgumentException('The quantity multiplier must be a positive finite number.');
        }
    }
}
