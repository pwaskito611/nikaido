<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Model\MarketPairCashFlow;
use App\Feature\CashFlows\Repository\MarketPairCashFlowRepository;

class UpdateMarketPairCashFlowAction
{
    public function __construct(private readonly MarketPairCashFlowRepository $cashFlows) {}

    public function execute(int $id, array $attributes): MarketPairCashFlow
    {
        return $this->cashFlows->update($id, $attributes);
    }
}
