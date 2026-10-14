<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPairs;

use App\Features\Engine\Repositories\MarketPairRepository;
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
