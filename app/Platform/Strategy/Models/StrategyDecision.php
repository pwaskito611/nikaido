<?php

namespace App\Platform\Strategy\Models;

use App\Platform\Strategy\Enums\StrategySignal;

class StrategyDecision
{
    /**
     * Entry decisions must include a positive quantity. The engine interprets it
     * using the unit configured on the selected market pair.
     */
    public function __construct(
        public readonly StrategySignal $signal,
        public readonly ?float $stopLoss,
        public readonly ?float $takeProfit,
        public readonly int|float|string|null $quantity = null,
    ) {}
}
