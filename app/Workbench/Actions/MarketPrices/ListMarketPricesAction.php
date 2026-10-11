<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPrices;

use App\Engine\Repositories\MarketPriceRepository;
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
