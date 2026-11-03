<?php

declare(strict_types=1);

namespace App\Feature\MarketPrice\Controller;

use App\Feature\MarketPrice\Action\CreateMarketPriceAction;
use App\Feature\MarketPrice\Action\DeleteMarketPriceAction;
use App\Feature\MarketPrice\Action\LoadMarketPriceFormPageAction;
use App\Feature\MarketPrice\Action\LoadMarketPriceListPageAction;
use App\Feature\MarketPrice\Action\UpdateMarketPriceAction;
use App\Feature\MarketPrice\Action\ViewMarketPriceAction;
use App\Feature\MarketPrice\Request\MarketPriceFiltersRequest;
use App\Feature\MarketPrice\Request\MarketPriceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketPriceController
{
    public function index(
        MarketPriceFiltersRequest $request,
        LoadMarketPriceListPageAction $loadPage,
    ): View {
        $filters = $request->validated();
        $pairId = isset($filters['pair_id']) ? (int) $filters['pair_id'] : null;
        $timeframeTypeId = isset($filters['timeframe_type_id']) ? (int) $filters['timeframe_type_id'] : null;

        return view('workbench.market-prices.index', $loadPage->execute($pairId, $timeframeTypeId));
    }

    public function create(LoadMarketPriceFormPageAction $loadPage): View
    {
        return view('workbench.market-prices.form', $loadPage->execute());
    }

    public function store(MarketPriceRequest $request, CreateMarketPriceAction $action): RedirectResponse
    {
        $price = $action->execute($request->validated());

        return redirect()->route('workbench.market-prices.show', $price)
            ->with('status', 'Market price created successfully.');
    }

    public function show(int $marketPrice, ViewMarketPriceAction $action): View
    {
        return view('workbench.market-prices.show', [
            'marketPrice' => $action->execute($marketPrice),
        ]);
    }

    public function edit(
        int $marketPrice,
        LoadMarketPriceFormPageAction $loadPage,
    ): View {
        return view('workbench.market-prices.form', $loadPage->execute($marketPrice));
    }

    public function update(MarketPriceRequest $request, int $marketPrice, UpdateMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice, $request->validated());

        return redirect()->route('workbench.market-prices.show', $marketPrice)
            ->with('status', 'Market price updated successfully.');
    }

    public function destroy(int $marketPrice, DeleteMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice);

        return redirect()->route('workbench.market-prices.index')
            ->with('status', 'Market price deleted successfully.');
    }
}
