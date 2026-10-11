<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Engine\Models\MarketType;
use App\Workbench\Actions\MarketTypes\CreateMarketTypeAction;
use App\Workbench\Actions\MarketTypes\DeleteMarketTypeAction;
use App\Workbench\Actions\MarketTypes\ListMarketTypesAction;
use App\Workbench\Actions\MarketTypes\UpdateMarketTypeAction;
use App\Workbench\Actions\MarketTypes\ViewMarketTypeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, CreateMarketTypeAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:market_type,name'],
        ]);

        $action->execute(trim($validated['name']));

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type berhasil dibuat.');
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

    public function update(Request $request, int $marketType, UpdateMarketTypeAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('market_type', 'name')->ignore($marketType),
            ],
        ]);

        $action->execute($marketType, trim($validated['name']));

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type berhasil diperbarui.');
    }

    public function destroy(int $marketType, DeleteMarketTypeAction $action): RedirectResponse
    {
        $action->execute($marketType);

        return redirect()->route('workbench.market-types.index')->with('status', 'Market type berhasil dihapus.');
    }
}
