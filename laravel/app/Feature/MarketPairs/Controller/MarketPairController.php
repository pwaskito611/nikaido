<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Controller;

use App\Feature\MarketPairs\Action\CreateMarketPairAction;
use App\Feature\MarketPairs\Action\DeleteMarketPairAction;
use App\Feature\MarketPairs\Action\ListMarketPairsAction;
use App\Feature\MarketPairs\Action\LoadMarketPairFormPageAction;
use App\Feature\MarketPairs\Action\UpdateMarketPairAction;
use App\Feature\MarketPairs\Action\ViewMarketPairAction;
use App\Feature\MarketPairs\Data\MarketPairDefinition;
use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use App\Feature\MarketPairs\Request\MarketPairRequest;
use Exact\Type\Option\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarketPairController
{
    public function index(ListMarketPairsAction $action): View
    {
        return view('workbench.market-pairs.index', ['marketPairs' => $action->execute()]);
    }

    public function create(LoadMarketPairFormPageAction $loadForm): View
    {
        return view('workbench.market-pairs.form', $loadForm->execute());
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
        LoadMarketPairFormPageAction $loadForm,
    ): View {
        return view('workbench.market-pairs.form', $loadForm->execute($marketPair));
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
