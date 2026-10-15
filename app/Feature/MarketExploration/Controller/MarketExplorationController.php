<?php

declare(strict_types=1);

namespace App\Feature\MarketExploration\Controller;

use App\Feature\MarketExploration\Action\LoadMarketExplorationPageAction;
use App\Feature\MarketExploration\Request\MarketExplorationRequest;
use Illuminate\View\View;

class MarketExplorationController
{
    public function index(
        MarketExplorationRequest $request,
        LoadMarketExplorationPageAction $loadPage,
    ): View {
        $filters = $request->validated();
        $selectedPairId = isset($filters['pair_id']) ? (int) $filters['pair_id'] : null;
        $selectedTimeframeId = isset($filters['timeframe_type_id']) ? (int) $filters['timeframe_type_id'] : null;

        return view('workbench.market-exploration.index', $loadPage->execute(
            $selectedPairId,
            $selectedTimeframeId,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        ));
    }
}
