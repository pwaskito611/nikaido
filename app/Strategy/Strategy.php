<?php

namespace App\Strategy;

use App\Engine\Models\MarketPrice;
use Illuminate\Support\Collection;

class Strategy
{
    /**
     * @param  Collection<int, MarketPrice>  $history  Candles before the current candle.
     */
    public function execute(MarketPrice $candle, Collection $history): bool
    {
        return false;
    }
}
