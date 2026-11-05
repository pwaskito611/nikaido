<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Model\MarketPairCashFlow;
use App\Feature\CashFlows\Repository\MarketPairCashFlowRepository;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Exact\Collection\Vec;

class LoadMarketPairCashFlowsPageAction
{
    public function __construct(
        private readonly MarketPairCashFlowRepository $cashFlows,
        private readonly MarketPairRepository $marketPairs,
    ) {}

    public function index(?int $marketPairId = null): array
    {
        return [
            'cashFlows' => $this->cashFlows->paginate($marketPairId),
            'marketPairs' => $this->marketPairs->all(),
            'selectedMarketPairId' => $marketPairId,
        ];
    }

    /** @return array{cashFlow: MarketPairCashFlow, marketPairs: Vec<MarketPair>} */
    public function form(?int $cashFlowId = null): array
    {
        return [
            'cashFlow' => $cashFlowId === null
                ? new MarketPairCashFlow
                : $this->cashFlows->findOrFail($cashFlowId),
            'marketPairs' => $this->marketPairs->all(),
        ];
    }

    public function show(int $cashFlowId): MarketPairCashFlow
    {
        return $this->cashFlows->findOrFail($cashFlowId);
    }
}
