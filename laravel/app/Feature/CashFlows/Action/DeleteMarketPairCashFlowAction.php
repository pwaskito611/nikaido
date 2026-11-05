<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Repository\MarketPairCashFlowRepository;

class DeleteMarketPairCashFlowAction
{
    public function __construct(private readonly MarketPairCashFlowRepository $cashFlows) {}

    public function execute(int $id): void
    {
        $this->cashFlows->delete($id);
    }
}
