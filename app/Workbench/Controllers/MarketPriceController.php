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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketPriceController
{
    public function index(
        Request $request,
        ListMarketPricesAction $action,
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        $filters = $request->validate([
            'pair_id' => ['nullable', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['nullable', 'integer', 'exists:timeframe_type,id'],
        ]);
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

    public function store(Request $request, CreateMarketPriceAction $action): RedirectResponse
    {
        $price = $action->execute($this->validateMarketPrice($request));

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

    public function update(Request $request, int $marketPrice, UpdateMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice, $this->validateMarketPrice($request, $marketPrice));

        return redirect()->route('workbench.market-prices.show', $marketPrice)
            ->with('status', 'Market price berhasil diperbarui.');
    }

    public function destroy(int $marketPrice, DeleteMarketPriceAction $action): RedirectResponse
    {
        $action->execute($marketPrice);

        return redirect()->route('workbench.market-prices.index')
            ->with('status', 'Market price berhasil dihapus.');
    }

    /**
     * @return array<string, int|string|null>
     */
    private function validateMarketPrice(Request $request, ?int $ignoreId = null): array
    {
        $priceDate = $request->input('price_date');
        if (is_string($priceDate)) {
            $priceDate = str_replace('T', ' ', $priceDate);
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $priceDate) === 1) {
                $priceDate .= ':00';
            }
            $request->merge(['price_date' => $priceDate]);
        }

        $rules = [
            'pair_id' => ['required', 'integer', 'exists:market_pair,id'],
            'timeframe_type_id' => ['required', 'integer', 'exists:timeframe_type,id'],
            'price_date' => [
                'required',
                'date_format:Y-m-d H:i:s',
                Rule::unique('market_price', 'price_date')
                    ->where(fn ($query) => $query
                        ->where('pair_id', $request->input('pair_id'))
                        ->where('timeframe_type_id', $request->input('timeframe_type_id')))
                    ->ignore($ignoreId),
            ],
            'close' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ];

        foreach (['open', 'high', 'low', 'price_at_8'] as $field) {
            $rules[$field] = ['nullable', 'numeric', 'gt:0', 'decimal:0,6'];
        }
        foreach (['bid_open', 'bid_high', 'bid_low', 'bid_close', 'ask_open', 'ask_high', 'ask_low', 'ask_close'] as $field) {
            $rules[$field] = ['required', 'numeric', 'gt:0', 'decimal:0,6'];
        }

        return $request->validate($rules);
    }
}
