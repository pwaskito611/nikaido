<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketPrice\Repository\MarketPriceRepository;

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
