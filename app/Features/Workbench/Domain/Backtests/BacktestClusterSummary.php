<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\Backtests;

final readonly class BacktestClusterSummary
{
    public function __construct(
        public int $orders,
        public int $closed,
        public int $wins,
        public int $losses,
        public float $netProfitLoss,
        public float $winRate,
        public float $capitalStart,
        public float $capitalEnd,
    ) {}

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return [
            'orders' => $this->orders,
            'closed' => $this->closed,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'net_profit_loss' => $this->netProfitLoss,
            'win_rate' => $this->winRate,
            'capital_start' => $this->capitalStart,
            'capital_end' => $this->capitalEnd,
        ];
    }
}
