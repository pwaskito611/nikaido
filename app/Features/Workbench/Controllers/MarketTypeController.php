<?php

declare(strict_types=1);

namespace App\Features\Workbench\Controllers;

use App\Features\Engine\Models\MarketType;
use App\Features\Workbench\Actions\MarketTypes\CreateMarketTypeAction;
use App\Features\Workbench\Actions\MarketTypes\DeleteMarketTypeAction;
use App\Features\Workbench\Actions\MarketTypes\ListMarketTypesAction;
use App\Features\Workbench\Actions\MarketTypes\UpdateMarketTypeAction;
use App\Features\Workbench\Actions\MarketTypes\ViewMarketTypeAction;
use App\Features\Workbench\Requests\MarketTypeRequest;
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
