<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPrices;

use App\Engine\Repositories\MarketPriceRepository;

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
