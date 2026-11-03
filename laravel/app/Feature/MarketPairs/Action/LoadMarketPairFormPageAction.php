<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketTypes\Action\ListMarketTypesAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoadMarketPairFormPageAction
{
    public function __construct(
        private readonly ListMarketTypesAction $listMarketTypes,
        private readonly ViewMarketPairAction $viewMarketPair,
    ) {}

    /**
     * @return array{marketPair: MarketPair, marketTypes: LengthAwarePaginator}
     */
    public function execute(?int $marketPairId = null): array
    {
        return [
            'marketPair' => $marketPairId === null
                ? new MarketPair
                : $this->viewMarketPair->execute($marketPairId),
            'marketTypes' => $this->listMarketTypes->execute(),
        ];
    }
}
