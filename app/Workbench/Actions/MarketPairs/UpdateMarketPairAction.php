<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPairs;

use App\Engine\Models\MarketPair;
use App\Engine\Repositories\MarketPairRepository;

class UpdateMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(int $id, int $marketTypeId, string $base, string $quote): MarketPair
    {
        return $this->marketPairRepository->update($id, [
            'market_type_id' => $marketTypeId,
            'base' => strtoupper($base),
            'quote' => strtoupper($quote),
        ]);
    }
}
