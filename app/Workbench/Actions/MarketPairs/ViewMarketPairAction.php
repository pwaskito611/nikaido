<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPairs;

use App\Engine\Models\MarketPair;
use App\Engine\Repositories\MarketPairRepository;

class ViewMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(int $id): MarketPair
    {
        return $this->marketPairRepository->findOrFail($id);
    }
}
