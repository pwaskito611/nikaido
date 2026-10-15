<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Type\Option\Option;
use InvalidArgumentException;

final readonly class MonteCarloTrade
{
    /**
     * @param  Option<float>  $capitalAtOpen
     */
    public function __construct(
        public float $profitLoss,
        public Option $capitalAtOpen,
    ) {
        if (! is_finite($profitLoss)) {
            throw new InvalidArgumentException('Monte Carlo trade P/L must be finite.');
        }
        if ($capitalAtOpen->exists(static fn (float $capital): bool => ! is_finite($capital))) {
            throw new InvalidArgumentException('Monte Carlo opening capital must be finite when present.');
        }
    }
}
