<?php

declare(strict_types=1);

namespace App\Features\Workbench\Controllers;

use App\Features\Engine\Models\MarketPair;
use App\Features\Workbench\Actions\MarketPairs\CreateMarketPairAction;
use App\Features\Workbench\Actions\MarketPairs\DeleteMarketPairAction;
use App\Features\Workbench\Actions\MarketPairs\ListMarketPairsAction;
use App\Features\Workbench\Actions\MarketPairs\UpdateMarketPairAction;
use App\Features\Workbench\Actions\MarketPairs\ViewMarketPairAction;
use App\Features\Workbench\Actions\MarketTypes\ListMarketTypesAction;
use App\Features\Workbench\Domain\MarketPairs\MarketPairDefinition;
use App\Features\Workbench\Domain\MarketPairs\MarketPairQuantityUnit;
use App\Features\Workbench\Requests\MarketPairRequest;
use Exact\Type\Option\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketPairController
{
    public function index(ListMarketPairsAction $action): View
    {
        return view('workbench.market-pairs.index', ['marketPairs' => $action->execute()]);
    }

    public function create(ListMarketTypesAction $marketTypes): View
    {
        return view('workbench.market-pairs.form', [
            'marketPair' => new MarketPair,
            'marketTypes' => $marketTypes->execute(),
        ]);
    }

    public function store(MarketPairRequest $request, CreateMarketPairAction $action): RedirectResponse
    {
        $pair = $action->execute($this->definitionFrom($request->validated()));

        return redirect()->route('workbench.market-pairs.show', $pair)->with('status', 'Market pair created successfully.');
    }

    public function show(int $marketPair, ViewMarketPairAction $action): View
    {
        return view('workbench.market-pairs.show', [
            'marketPair' => $action->execute($marketPair),
        ]);
    }

    public function edit(
        int $marketPair,
        ViewMarketPairAction $action,
        ListMarketTypesAction $marketTypes,
    ): View {
        return view('workbench.market-pairs.form', [
            'marketPair' => $action->execute($marketPair),
            'marketTypes' => $marketTypes->execute(),
        ]);
    }

    public function update(MarketPairRequest $request, int $marketPair, UpdateMarketPairAction $action): RedirectResponse
    {
        $action->execute($marketPair, $this->definitionFrom($request->validated()));

        return redirect()->route('workbench.market-pairs.show', $marketPair)->with('status', 'Market pair updated successfully.');
    }

    public function destroy(int $marketPair, DeleteMarketPairAction $action): RedirectResponse
    {
        $action->execute($marketPair);

        return redirect()->route('workbench.market-pairs.index')->with('status', 'Market pair deleted successfully.');
    }

    /**
     * @param  array{market_type_id: int|string, base: string, quote: string, quantity_unit: string, contract_size?: string|null}  $validated
     */
    private function definitionFrom(array $validated): MarketPairDefinition
    {
        return MarketPairDefinition::create(
            (int) $validated['market_type_id'],
            $validated['base'],
            $validated['quote'],
            MarketPairQuantityUnit::from($validated['quantity_unit']),
            Option::from(isset($validated['contract_size']) ? (string) $validated['contract_size'] : null),
        );
    }
}
