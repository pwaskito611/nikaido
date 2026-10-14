<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPairs;

use App\Features\Engine\Models\MarketPair;
use App\Features\Engine\Repositories\MarketPairRepository;
use App\Features\Workbench\Domain\MarketPairs\MarketPairDefinition;

class CreateMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(MarketPairDefinition $definition): MarketPair
    {
        return $this->marketPairRepository->create($definition);
    }
}
