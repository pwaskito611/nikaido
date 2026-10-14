<?php

declare(strict_types=1);

namespace App\Features\Workbench\Controllers;

use App\Features\Workbench\Actions\MarketExploration\LoadMarketExplorationCandlesAction;
use App\Features\Workbench\Actions\MarketPairs\ListAllMarketPairsAction;
use App\Features\Workbench\Actions\TimeframeTypes\ListAllTimeframeTypesAction;
use App\Features\Workbench\Requests\MarketExplorationRequest;
use Illuminate\View\View;

class MarketExplorationController
{
    public function index(
        MarketExplorationRequest $request,
        LoadMarketExplorationCandlesAction $loadCandles,
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        $filters = $request->validated();

        $candles = [];
        $selectedPairId = isset($filters['pair_id']) ? (int) $filters['pair_id'] : null;
        $selectedTimeframeId = isset($filters['timeframe_type_id']) ? (int) $filters['timeframe_type_id'] : null;
        if ($selectedPairId !== null && $selectedTimeframeId !== null && isset($filters['from'], $filters['to'])) {
            $candles = $loadCandles->execute(
                $selectedPairId,
                $selectedTimeframeId,
                $filters['from'],
                $filters['to'],
            );
        }

        return view('workbench.market-exploration.index', [
            'marketPairs' => $marketPairs->execute(),
            'timeframes' => $timeframes->execute(),
            'selectedPairId' => $selectedPairId,
            'selectedTimeframeId' => $selectedTimeframeId,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'candles' => $candles,
            'hasSearched' => $selectedPairId !== null && $selectedTimeframeId !== null && isset($filters['from'], $filters['to']),
        ]);
    }
}
