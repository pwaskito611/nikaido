<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Illuminate\Database\Eloquent\Collection;

class ListAllMarketPairsAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    /**
     * @return Collection<int, MarketPair>
     */
    public function execute(): Collection
    {
        return $this->marketPairRepository->all();
    }
}
