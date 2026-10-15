<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Data\MarketPairDefinition;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;

class UpdateMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(int $id, MarketPairDefinition $definition): MarketPair
    {
        return $this->marketPairRepository->update($id, $definition);
    }
}
