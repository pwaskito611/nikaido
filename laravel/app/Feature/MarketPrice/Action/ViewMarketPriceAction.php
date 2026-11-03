<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketPrice\Repository\MarketPriceRepository;

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
