<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Exact\Collection\Vec;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoadMarketPriceListPageAction
{
    public function __construct(
        private readonly ListMarketPricesAction $listMarketPrices,
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPrices: LengthAwarePaginator,
     *     marketPairs: Vec<MarketPair>,
     *     timeframes: Vec<TimeframeType>,
     *     selectedPairId: ?int,
     *     selectedTimeframeId: ?int
     * }
     */
    public function execute(?int $pairId = null, ?int $timeframeTypeId = null): array
    {
        return [
            'marketPrices' => $this->listMarketPrices->execute($pairId, $timeframeTypeId),
            'marketPairs' => $this->listMarketPairs->execute(),
            'timeframes' => $this->listTimeframes->execute(),
            'selectedPairId' => $pairId,
            'selectedTimeframeId' => $timeframeTypeId,
        ];
    }
}
