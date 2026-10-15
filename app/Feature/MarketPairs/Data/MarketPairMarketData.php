<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use App\Feature\MarketPrice\Model\MarketPrice;
use Exact\Collection\Vec;

final readonly class MarketPairMarketData
{
    /** @param Vec<MarketPrice> $candles */
    public function __construct(
        public Vec $candles,
        public MarketPairQuantityUnit $quantityUnit,
        public string $contractSize,
    ) {}
}
