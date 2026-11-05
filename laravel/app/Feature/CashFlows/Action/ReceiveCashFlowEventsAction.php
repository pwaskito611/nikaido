<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\CashFlows\Repository\MarketPairCashFlowRepository;

class ReceiveCashFlowEventsAction
{
    public function __construct(private readonly MarketPairCashFlowRepository $cashFlows) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{imported: int, inserted: int, updated: int}
     */
    public function execute(CashFlowImportSession $session, array $events): array
    {
        return $this->cashFlows->import((int) $session->market_pair_id, $events);
    }
}
