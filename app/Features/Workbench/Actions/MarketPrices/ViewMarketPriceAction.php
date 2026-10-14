<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPrices;

use App\Features\Engine\Models\MarketPrice;
use App\Features\Engine\Repositories\MarketPriceRepository;

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
