<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Collection\Vec;

final readonly class SpecificPairBacktestResult
{
    /**
     * @param  Vec<BacktestDecision>  $decisions
     * @param  Vec<BacktestOrderHistoryEntry>  $orders
     * @param  Vec<array<string, int|float|string>>|null  $cashFlows
     */
    public function __construct(
        public Vec $decisions,
        public Vec $orders,
        public float $initialCapital,
        public string $quantityUnit,
        public float $unitsPerLot,
        public float $quantityMultiplier,
        public float $endingCapital,
        public BacktestExecutionConfiguration $executionConfiguration,
        public ?Vec $cashFlows = null,
    ) {}

}
