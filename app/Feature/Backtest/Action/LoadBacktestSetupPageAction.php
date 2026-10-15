<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketTypes\Action\ListAllMarketTypesAction;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Database\Eloquent\Collection;

class LoadBacktestSetupPageAction
{
    public function __construct(
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllMarketTypesAction $listMarketTypes,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPairs: Collection<int, MarketPair>,
     *     marketTypes: Collection<int, MarketType>,
     *     timeframes: Collection<int, TimeframeType>
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
