<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPairs;

use App\Features\Engine\Models\MarketPair;
use App\Features\Engine\Repositories\MarketPairRepository;

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
