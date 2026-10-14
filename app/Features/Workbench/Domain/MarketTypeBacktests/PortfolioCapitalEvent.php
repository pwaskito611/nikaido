<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\MarketTypeBacktests;

use Exact\Type\Option\Option;

final readonly class PortfolioCapitalEvent
{
    /**
     * @param  Option<int>  $fillIndex
     */
    public function __construct(
        public string $date,
        public bool $isExit,
        public int $orderIndex,
        public Option $fillIndex,
        public string $pair,
        public int $orderId,
        public int $sequence,
    ) {}
}
