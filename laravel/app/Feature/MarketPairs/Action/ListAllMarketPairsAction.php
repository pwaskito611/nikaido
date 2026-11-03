<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Exact\Collection\Vec;

class ListAllMarketPairsAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    /**
     * @return Vec<MarketPair>
     */
    public function execute(): Vec
    {
        return $this->marketPairRepository->all();
    }
}
