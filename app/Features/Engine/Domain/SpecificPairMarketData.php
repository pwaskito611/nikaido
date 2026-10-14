<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use App\Features\Engine\Models\MarketPrice;
use App\Features\Workbench\Domain\MarketPairs\MarketPairQuantityUnit;
use Exact\Collection\Vec;

final readonly class SpecificPairMarketData
{
    /** @param Vec<MarketPrice> $candles */
    public function __construct(
        public Vec $candles,
        public MarketPairQuantityUnit $quantityUnit,
        public string $contractSize,
    ) {}
}
