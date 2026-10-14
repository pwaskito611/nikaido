<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPrices;

use App\Features\Engine\Models\MarketPrice;
use App\Features\Engine\Repositories\MarketPriceRepository;

class CreateMarketPriceAction
{
    public function __construct(
        private readonly MarketPriceRepository $marketPriceRepository,
    ) {}

    /**
     * @param  array<string, int|string|null>  $attributes
     */
    public function execute(array $attributes): MarketPrice
    {
        return $this->marketPriceRepository->create($attributes);
    }
}
