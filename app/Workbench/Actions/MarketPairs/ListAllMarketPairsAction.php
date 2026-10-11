<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPairs;

use App\Engine\Models\MarketPair;
use App\Engine\Repositories\MarketPairRepository;
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
