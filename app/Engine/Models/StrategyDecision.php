<?php

namespace App\Engine\Models;

use App\Engine\Enums\StrategySignal;

class StrategyDecision
{
    public function __construct(
        public readonly StrategySignal $signal,
        public readonly ?float $stopLoss,
        public readonly ?float $takeProfit,
    ) {}
}
