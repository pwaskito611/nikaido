<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Controller;

use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\MarketTypes\Action\CreateMarketTypeAction;
use App\Feature\MarketTypes\Action\DeleteMarketTypeAction;
use App\Feature\MarketTypes\Action\ListMarketTypesAction;
use App\Feature\MarketTypes\Action\UpdateMarketTypeAction;
use App\Feature\MarketTypes\Action\ViewMarketTypeAction;
use App\Feature\MarketTypes\Request\MarketTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketTypeController
{
    public function index(ListMarketTypesAction $action): View
    {
        return view('workbench.market-types.index', ['marketTypes' => $action->execute()]);
    }

    public function create(): View
    {
        return view('workbench.market-types.form', ['marketType' => new MarketType]);
    }

    public function store(MarketTypeRequest $request, CreateMarketTypeAction $action): RedirectResponse
    {
        $action->execute($request->validated('name'));

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type created successfully.');
    }

    public function show(int $marketType, ViewMarketTypeAction $action): View
    {
        return view('workbench.market-types.show', [
            'marketType' => $action->execute($marketType),
        ]);
    }

    public function edit(int $marketType, ViewMarketTypeAction $action): View
    {
        return view('workbench.market-types.form', [
            'marketType' => $action->execute($marketType),
        ]);
    }

    public function update(MarketTypeRequest $request, int $marketType, UpdateMarketTypeAction $action): RedirectResponse
    {
        $action->execute($marketType, $request->validated('name'));

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type updated successfully.');
    }

    public function destroy(int $marketType, DeleteMarketTypeAction $action): RedirectResponse
    {
        $action->execute($marketType);

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type deleted successfully.');
    }
}
