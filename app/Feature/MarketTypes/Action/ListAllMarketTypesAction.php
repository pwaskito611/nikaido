<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\MarketTypes\Repository\MarketTypeRepository;
use Illuminate\Database\Eloquent\Collection;

class ListAllMarketTypesAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    /**
     * @return Collection<int, MarketType>
     */
    public function execute(): Collection
    {
        return $this->marketTypeRepository->all();
    }
}
