<?php

declare(strict_types=1);

namespace App\Platform\Strategy;

use Nikaido\Strategy\Models\StrategyDecision;
use Nikaido\Strategy\Models\StrategyExecutionContext;

class Strategy
{
    /**
     * @param  iterable<object>  $candles
     * @return iterable<int, StrategyDecision>
     */
    public function execute(iterable $candles, StrategyExecutionContext $context): iterable
    {
        return \Nikaido\Strategy\main($candles, $context);
    }
}
