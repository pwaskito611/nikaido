<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPrices;

use App\Features\Engine\Repositories\MarketPriceRepository;

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
