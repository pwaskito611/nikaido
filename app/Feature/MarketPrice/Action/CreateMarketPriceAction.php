<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketPrice\Repository\MarketPriceRepository;

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
