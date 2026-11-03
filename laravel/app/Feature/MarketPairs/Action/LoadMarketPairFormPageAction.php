<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\MarketTypes\Action\ListMarketTypesAction;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Exact\Collection\Vec;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoadMarketPairFormPageAction
{
    public function __construct(
        private readonly ListMarketTypesAction $listMarketTypes,
        private readonly ViewMarketPairAction $viewMarketPair,
        private readonly MarketPairRepository $marketPairs,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{marketPair: MarketPair, marketTypes: LengthAwarePaginator, availableMarketPairs: Vec<MarketPair>, availableTimeframes: Vec<TimeframeType>}
     */
    public function execute(?int $marketPairId = null): array
    {
        return [
            'marketPair' => $marketPairId === null
                ? new MarketPair
                : $this->viewMarketPair->execute($marketPairId),
            'marketTypes' => $this->listMarketTypes->execute(),
            'availableMarketPairs' => $this->marketPairs->all(),
            'availableTimeframes' => $this->listTimeframes->execute(),
        ];
    }
}
