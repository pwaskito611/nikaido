<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Controller;

use App\Feature\CashFlows\Action\CreateMarketPairCashFlowAction;
use App\Feature\CashFlows\Action\DeleteMarketPairCashFlowAction;
use App\Feature\CashFlows\Action\LoadMarketPairCashFlowsPageAction;
use App\Feature\CashFlows\Action\UpdateMarketPairCashFlowAction;
use App\Feature\CashFlows\Request\MarketPairCashFlowFiltersRequest;
use App\Feature\CashFlows\Request\MarketPairCashFlowRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketPairCashFlowController
{
    public function index(
        MarketPairCashFlowFiltersRequest $request,
        LoadMarketPairCashFlowsPageAction $loadPage,
    ): View {
        $filters = $request->validated();
        $pairId = isset($filters['market_pair_id']) ? (int) $filters['market_pair_id'] : null;

        return view('workbench.cash-flows.index', $loadPage->index($pairId));
    }

    public function create(LoadMarketPairCashFlowsPageAction $loadPage): View
    {
        return view('workbench.cash-flows.form', $loadPage->form());
    }

    public function store(
        MarketPairCashFlowRequest $request,
        CreateMarketPairCashFlowAction $create,
    ): RedirectResponse {
        $cashFlow = $create->execute($request->validated());

        return redirect()->route('workbench.cash-flows.show', $cashFlow)
            ->with('status', 'Cash flow event created successfully.');
    }

    public function show(int $cashFlow, LoadMarketPairCashFlowsPageAction $loadPage): View
    {
        return view('workbench.cash-flows.show', ['cashFlow' => $loadPage->show($cashFlow)]);
    }

    public function edit(int $cashFlow, LoadMarketPairCashFlowsPageAction $loadPage): View
    {
        return view('workbench.cash-flows.form', $loadPage->form($cashFlow));
    }

    public function update(
        MarketPairCashFlowRequest $request,
        int $cashFlow,
        UpdateMarketPairCashFlowAction $update,
    ): RedirectResponse {
        $update->execute($cashFlow, $request->validated());

        return redirect()->route('workbench.cash-flows.show', $cashFlow)
            ->with('status', 'Cash flow event updated successfully.');
    }

    public function destroy(int $cashFlow, DeleteMarketPairCashFlowAction $delete): RedirectResponse
    {
        $delete->execute($cashFlow);

        return redirect()->route('workbench.cash-flows.index')
            ->with('status', 'Cash flow event deleted successfully.');
    }
}
