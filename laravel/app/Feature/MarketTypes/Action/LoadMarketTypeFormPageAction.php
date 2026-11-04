<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Model\MarketType;

class LoadMarketTypeFormPageAction
{
    public function __construct(
        private readonly ViewMarketTypeAction $viewMarketType,
    ) {}

    public function execute(?int $marketTypeId = null): MarketType
    {
        return $marketTypeId === null
            ? new MarketType
            : $this->viewMarketType->execute($marketTypeId);
    }
}
