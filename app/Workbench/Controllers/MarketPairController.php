<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Engine\Models\MarketPair;
use App\Workbench\Actions\MarketPairs\CreateMarketPairAction;
use App\Workbench\Actions\MarketPairs\DeleteMarketPairAction;
use App\Workbench\Actions\MarketPairs\ListMarketPairsAction;
use App\Workbench\Actions\MarketPairs\UpdateMarketPairAction;
use App\Workbench\Actions\MarketPairs\ViewMarketPairAction;
use App\Workbench\Actions\MarketTypes\ListMarketTypesAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, CreateMarketPairAction $action): RedirectResponse
    {
        $validated = $this->validateMarketPair($request);

        $pair = $action->execute(
            (int) $validated['market_type_id'],
            $validated['base'],
            $validated['quote'],
        );

        return redirect()->route('workbench.market-pairs.show', $pair)->with('status', 'Market pair berhasil dibuat.');
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

    public function update(Request $request, int $marketPair, UpdateMarketPairAction $action): RedirectResponse
    {
        $validated = $this->validateMarketPair($request, $marketPair);

        $action->execute(
            $marketPair,
            (int) $validated['market_type_id'],
            $validated['base'],
            $validated['quote'],
        );

        return redirect()->route('workbench.market-pairs.show', $marketPair)->with('status', 'Market pair berhasil diperbarui.');
    }

    public function destroy(int $marketPair, DeleteMarketPairAction $action): RedirectResponse
    {
        $action->execute($marketPair);

        return redirect()->route('workbench.market-pairs.index')->with('status', 'Market pair berhasil dihapus.');
    }

    /**
     * @return array{market_type_id: string, base: string, quote: string}
     */
    private function validateMarketPair(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'base' => strtoupper(trim((string) $request->input('base'))),
            'quote' => strtoupper(trim((string) $request->input('quote'))),
        ]);

        return $request->validate([
            'market_type_id' => ['required', 'integer', 'exists:market_type,id'],
            'base' => [
                'required',
                'string',
                'max:10',
                Rule::unique('market_pair', 'base')
                    ->where(fn ($query) => $query
                        ->where('market_type_id', $request->input('market_type_id'))
                        ->where('quote', $request->input('quote')))
                    ->ignore($ignoreId),
            ],
            'quote' => ['required', 'string', 'max:10'],
        ]);
    }
}
