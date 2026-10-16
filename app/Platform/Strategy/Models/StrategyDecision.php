<?php

namespace App\Platform\Strategy\Models;

use App\Platform\Strategy\Enums\StrategySignal;
use InvalidArgumentException;

class StrategyDecision
{
    /**
     * Entry decisions must include a positive quantity. A non-null limit price
     * requests a limit entry in the direction of the long or short signal.
     */
    public function __construct(
        public readonly StrategySignal $signal,
        public readonly ?float $stopLoss,
        public readonly ?float $takeProfit,
        public readonly int|float|string|null $quantity = null,
        public readonly ?float $limitPrice = null,
    ) {
        if ($limitPrice !== null && (
            ! in_array($signal, [StrategySignal::Long, StrategySignal::Short], true)
            || ! is_finite($limitPrice)
            || $limitPrice <= 0
        )) {
            throw new InvalidArgumentException('A limit entry requires a positive finite price and a long or short signal.');
        }
    }
}
