<?php

namespace App\Platform\Strategy;

use App\Features\Engine\Models\MarketPrice;
use App\Platform\Strategy\Actions\GenerateMovingAverageCrossDecisionsAction;
use App\Platform\Strategy\Models\StrategyDecision;
use App\Platform\Strategy\Models\StrategyExecutionContext;

class Strategy
{
    public function __construct(
        private readonly GenerateMovingAverageCrossDecisionsAction $generateMovingAverageCrossDecisions,
    ) {}

    /**
     * @param  iterable<MarketPrice>  $candles  Candles must be chronological and include warm-up data.
     * @return iterable<int, StrategyDecision>
     */
    public function execute(iterable $candles, StrategyExecutionContext $context): iterable
    {
        return $this->generateMovingAverageCrossDecisions->execute($candles, $context);
    }
}
