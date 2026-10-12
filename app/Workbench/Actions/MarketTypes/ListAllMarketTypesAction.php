<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypes;

use App\Engine\Models\MarketType;
use App\Engine\Repositories\MarketTypeRepository;
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
