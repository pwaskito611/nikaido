<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use App\Feature\MarketPrice\Model\MarketPrice;
use Exact\Collection\Vec;

final readonly class MarketPairMarketData
{
    /**
     * @param  Vec<MarketPrice>  $candles
     * @param  list<RelatedMarketPairMarketData>  $relatedMarketPairs
     */
    public function __construct(
        public Vec $candles,
        public MarketPairQuantityUnit $quantityUnit,
        public string $contractSize,
        public ?int $marketPairId = null,
        public ?string $base = null,
        public ?string $quote = null,
        public array $relatedMarketPairs = [],
        public ?CfdContractSpecification $contractSpecification = null,
    ) {}
}
