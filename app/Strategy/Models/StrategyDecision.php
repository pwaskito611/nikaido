<?php

namespace App\Strategy\Models;

use App\Strategy\Enums\StrategySignal;

class StrategyDecision
{
    public function __construct(
        public readonly StrategySignal $signal,
        public readonly ?float $stopLoss,
        public readonly ?float $takeProfit,
    ) {}
}
