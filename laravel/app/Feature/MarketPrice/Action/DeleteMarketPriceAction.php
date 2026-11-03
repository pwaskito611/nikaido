<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPrice\Repository\MarketPriceRepository;

class DeleteMarketPriceAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPriceRepository,
    ) {}

    public function execute(int $id): void
    {
        $this->marketPriceRepository->delete($id);
    }
}
