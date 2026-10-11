<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPrices;

use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPriceRepository;

class ViewMarketPriceAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPriceRepository,
    ) {}

    public function execute(int $id): MarketPrice
    {
        return $this->marketPriceRepository->findOrFail($id);
    }
}
