<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use App\Features\Engine\Models\BacktestOrderHistoryEntry;
use Exact\Collection\Vec;

final readonly class SpecificPairBacktestResult
{
    /**
     * @param  Vec<BacktestDecision>  $decisions
     * @param  Vec<BacktestOrderHistoryEntry>  $orders
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
    ) {}

}
