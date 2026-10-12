<?php

declare(strict_types=1);

namespace App\Engine\Config;

use InvalidArgumentException;

class PartialFillConfig
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly float $ratio = 1.0,
        public readonly string $unfilledOrderPolicy = 'keep',
    ) {
        if (! is_finite($ratio) || $ratio <= 0 || $ratio > 1) {
            throw new InvalidArgumentException('Partial fill ratio must be greater than 0 and at most 1.');
        }

        if (! in_array($unfilledOrderPolicy, ['keep', 'cancel'], true)) {
            throw new InvalidArgumentException('Unfilled order policy must be keep or cancel.');
        }
    }
}
