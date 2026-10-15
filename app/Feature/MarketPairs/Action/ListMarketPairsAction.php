<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMarketPairsAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(): LengthAwarePaginator
    {
        return $this->marketPairRepository->paginate();
    }
}
