<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\MarketTypes\Repository\MarketTypeRepository;

class ViewMarketTypeAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(int $id): MarketType
    {
        return $this->marketTypeRepository->findOrFail($id);
    }
}
