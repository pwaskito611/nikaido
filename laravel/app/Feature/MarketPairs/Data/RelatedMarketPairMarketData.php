<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

use App\Feature\MarketPrice\Model\MarketPrice;
use Exact\Collection\Vec;

final readonly class RelatedMarketPairMarketData
{
    /**
     * @param  Vec<MarketPrice>  $candles
     */
    public function __construct(
        public int $marketPairId,
        public string $base,
        public string $quote,
        public ?int $timeframeTypeId,
        public Vec $candles,
    ) {}
}
