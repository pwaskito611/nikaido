<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Models;

use InvalidArgumentException;

class StrategyExecutionContext
{
    /**
     * @param  array<int, RelatedMarketPairData>  $relatedMarketPairs  Keyed by related market pair ID.
     */
    public function __construct(
        public float $currentCapital,
        public readonly string $quantityUnit,
        public readonly float $quantityMultiplier,
        public readonly ?int $primaryMarketPairId = null,
        public readonly ?string $primaryMarketPairSymbol = null,
        public readonly array $relatedMarketPairs = [],
        public readonly ?int $primaryTimeframeTypeId = null,
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

        if ($primaryMarketPairId !== null && $primaryMarketPairId <= 0) {
            throw new InvalidArgumentException('The primary market pair ID must be positive.');
        }
        if ($primaryTimeframeTypeId !== null && $primaryTimeframeTypeId <= 0) {
            throw new InvalidArgumentException('The primary timeframe type ID must be positive.');
        }
        foreach ($relatedMarketPairs as $marketPairId => $relatedMarketPair) {
            if (! is_int($marketPairId)
                || $marketPairId <= 0
                || ! $relatedMarketPair instanceof RelatedMarketPairData
                || $relatedMarketPair->marketPairId !== $marketPairId) {
                throw new InvalidArgumentException('Related market pairs must be uniquely keyed by market pair ID.');
            }
        }
    }
}
