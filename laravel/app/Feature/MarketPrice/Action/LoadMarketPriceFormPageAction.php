<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Exact\Collection\Vec;

class LoadMarketPriceFormPageAction
{
    public function __construct(
        private readonly ViewMarketPriceAction $viewMarketPrice,
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPrice: MarketPrice,
     *     marketPairs: Vec<MarketPair>,
     *     timeframes: Vec<TimeframeType>
     * }
     */
    public function execute(?int $marketPriceId = null): array
    {
        return [
            'marketPrice' => $marketPriceId === null
                ? new MarketPrice
                : $this->viewMarketPrice->execute($marketPriceId),
            'marketPairs' => $this->listMarketPairs->execute(),
            'timeframes' => $this->listTimeframes->execute(),
        ];
    }
}
