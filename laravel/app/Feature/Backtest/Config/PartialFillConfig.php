<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

use InvalidArgumentException;

class PartialFillConfig
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly float $ratio = 1.0,
        public readonly UnfilledOrderPolicy $unfilledOrderPolicy = UnfilledOrderPolicy::Keep,
        public readonly PartialFillMode $mode = PartialFillMode::Fixed,
        public readonly float $maxParticipationRate = 0.1,
        public readonly string $volumeSource = 'traded_volume',
        public readonly string $executionVolumePeriod = 'candle',
    ) {
        if (! is_finite($ratio) || $ratio <= 0 || $ratio > 1) {
            throw new InvalidArgumentException('Partial fill ratio must be greater than 0 and at most 1.');
        }
        if (! is_finite($maxParticipationRate) || $maxParticipationRate < 0 || $maxParticipationRate > 1) {
            throw new InvalidArgumentException('Maximum volume participation rate must be between 0 and 1.');
        }
        if ($volumeSource !== 'traded_volume') {
            throw new InvalidArgumentException('Volume-based partial fills support only explicitly marked traded volume.');
        }
        if ($executionVolumePeriod !== 'candle') {
            throw new InvalidArgumentException('Volume-based partial fills are supported only per selected-timeframe candle.');
        }
    }
}
