<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPrices;

use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPriceRepository;

class UpdateMarketPriceAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPriceRepository,
    ) {}

    /**
     * @param  array<string, int|string|null>  $attributes
     */
    public function execute(int $id, array $attributes): MarketPrice
    {
        return $this->marketPriceRepository->update($id, $attributes);
    }
}
