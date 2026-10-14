<?php

declare(strict_types=1);

namespace App\Features\Engine\Config;

use InvalidArgumentException;

class FixedSlippageConfig
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly float $pips = 0.0,
        public readonly ?float $pipSize = null,
    ) {
        if (! is_finite($pips) || $pips < 0) {
            throw new InvalidArgumentException('Slippage must be a non-negative finite number of pips.');
        }

        if ($pipSize !== null && (! is_finite($pipSize) || $pipSize <= 0)) {
            throw new InvalidArgumentException('Slippage pip size must be a positive finite price increment.');
        }
    }
}
