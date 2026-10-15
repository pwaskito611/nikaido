<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Repository\MarketTypeRepository;
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
