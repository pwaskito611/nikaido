<?php

namespace App\Strategy;

use App\Engine\Models\MarketPrice;
use App\Strategy\Models\StrategyDecision;
use App\Strategy\Strategies\MovingAverageCrossStrategy;
use Exact\Collection\Vec;

class Strategy
{
    public function __construct(
        private readonly MovingAverageCrossStrategy $movingAverageCross,
    ) {}

    /**
     * @param  iterable<MarketPrice>  $candles  Candles must be chronological and include warm-up data.
     * @return Vec<StrategyDecision>
     */
    public function execute(iterable $candles): Vec
    {
        return $this->movingAverageCross->execute($candles);
    }
}
