<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypes;

use App\Features\Engine\Models\MarketType;
use App\Features\Engine\Repositories\MarketTypeRepository;
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
