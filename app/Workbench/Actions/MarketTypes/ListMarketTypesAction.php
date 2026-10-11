<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypes;

use App\Engine\Repositories\MarketTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMarketTypesAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(): LengthAwarePaginator
    {
        return $this->marketTypeRepository->paginate();
    }
}
