<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\MarketTypeBacktests;

use Exact\Collection\Vec;

final readonly class PortfolioCapitalSynchronizationResult
{
    /** @param Vec<PortfolioManagedOrder> $orders */
    public function __construct(
        public Vec $orders,
        public float $endingCapital,
    ) {}

    /** @return array{orders: list<array<string, mixed>>, ending_capital: float} */
    public function toArray(): array
    {
        return [
            'orders' => $this->orders
                ->map(static fn (PortfolioManagedOrder $order): array => $order->toArray())
                ->toArray(),
            'ending_capital' => $this->endingCapital,
        ];
    }
}
