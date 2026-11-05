<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Repository\MarketPairCashFlowRepository;
use DateTimeInterface;
use Nikaido\Strategy\Models\CashFlowDataCollection;

class LoadMarketPairCashFlowsForBacktestAction
{
    public function __construct(private readonly MarketPairCashFlowRepository $cashFlows) {}

    public function execute(
        int $marketPairId,
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): CashFlowDataCollection {
        return $this->cashFlows->forBacktest($marketPairId, $from, $to);
    }
}
