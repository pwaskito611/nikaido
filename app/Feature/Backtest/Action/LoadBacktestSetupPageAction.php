<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketTypes\Action\ListAllMarketTypesAction;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Exact\Collection\Vec;

class LoadBacktestSetupPageAction
{
    public function __construct(
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllMarketTypesAction $listMarketTypes,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPairs: Vec<MarketPair>,
     *     marketTypes: Vec<MarketType>,
     *     timeframes: Vec<TimeframeType>
     * }
     */
    public function execute(): array
    {
        return [
            'marketPairs' => $this->listMarketPairs->execute(),
            'marketTypes' => $this->listMarketTypes->execute(),
            'timeframes' => $this->listTimeframes->execute(),
        ];
    }
}
