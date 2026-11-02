<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\MarketTypes\Repository\MarketTypeRepository;
use Exact\Collection\Vec;

class ListAllMarketTypesAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    /**
     * @return Vec<MarketType>
     */
    public function execute(): Vec
    {
        return $this->marketTypeRepository->all();
    }
}
