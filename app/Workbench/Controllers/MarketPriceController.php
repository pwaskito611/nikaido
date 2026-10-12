<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Engine\Models\MarketPrice;
use App\Workbench\Actions\MarketPairs\ListAllMarketPairsAction;
use App\Workbench\Actions\MarketPrices\CreateMarketPriceAction;
use App\Workbench\Actions\MarketPrices\DeleteMarketPriceAction;
use App\Workbench\Actions\MarketPrices\ListMarketPricesAction;
use App\Workbench\Actions\MarketPrices\UpdateMarketPriceAction;
use App\Workbench\Actions\MarketPrices\ViewMarketPriceAction;
use App\Workbench\Actions\TimeframeTypes\ListAllTimeframeTypesAction;
use App\Workbench\Requests\MarketPriceFiltersRequest;
use App\Workbench\Requests\MarketPriceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketPriceController
{
    public function index(
        MarketPriceFiltersRequest $request,
        ListMarketPricesAction $action,
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        $filters = $request->validated();
        $pairId = isset($filters['pair_id']) ? (int) $filters['pair_id'] : null;
        $timeframeTypeId = isset($filters['timeframe_type_id']) ? (int) $filters['timeframe_type_id'] : null;

        return view('workbench.market-prices.index', [
            'marketPrices' => $action->execute($pairId, $timeframeTypeId),
            'marketPairs' => $marketPairs->execute(),
            'timeframes' => $timeframes->execute(),
            'selectedPairId' => $pairId,
            'selectedTimeframeId' => $timeframeTypeId,
        ]);
    }

    public function create(
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        return view('workbench.market-prices.form', [
            'marketPrice' => new MarketPrice,
            'marketPairs' => $marketPairs->execute(),
            'timeframes' => $timeframes->execute(),
        ]);
    }

    public function store(MarketPriceRequest $request, CreateMarketPriceAction $action): RedirectResponse
    {
        $price = $action->execute($request->validated());

        return redirect()->route('workbench.market-prices.show', $price)
            ->with('status', 'Market price berhasil dibuat.');
    }

    public function show(int $marketPrice, ViewMarketPriceAction $action): View
    {
        return view('workbench.market-prices.show', [
            'marketPrice' => $action->execute($marketPrice),
        ]);
    }

    public function edit(
        int $marketPrice,
        ViewMarketPriceAction $action,
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        return view('workbench.market-prices.form', [
            'marketPrice' => $action->execute($marketPrice),
            'marketPairs' => $marketPairs->execute(),
            'timeframes' => $timeframes->execute(),
        ]);
    }

    public function update(MarketPriceRequest $request, int $marketPrice, UpdateMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice, $request->validated());

        return redirect()->route('workbench.market-prices.show', $marketPrice)
            ->with('status', 'Market price berhasil diperbarui.');
    }

    public function destroy(int $marketPrice, DeleteMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice);

        return redirect()->route('workbench.market-prices.index')
            ->with('status', 'Market price berhasil dihapus.');
    }
}
