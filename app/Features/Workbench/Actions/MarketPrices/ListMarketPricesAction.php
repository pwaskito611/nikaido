<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPrices;

use App\Features\Engine\Repositories\MarketPriceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMarketPricesAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPriceRepository,
    ) {}

    public function execute(?int $pairId = null, ?int $timeframeTypeId = null): LengthAwarePaginator
    {
        return $this->marketPriceRepository->paginate($pairId, $timeframeTypeId);
    }
}
