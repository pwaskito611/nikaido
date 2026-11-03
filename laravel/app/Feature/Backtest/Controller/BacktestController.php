<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Controller;

use App\Feature\Backtest\Action\InitiateMarketTypeBacktestAction;
use App\Feature\Backtest\Action\LoadBacktestSetupPageAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypeBacktestRunAction;
use App\Feature\Backtest\Action\RunConfiguredPairBacktestAction;
use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Data\BacktestOrderHistoryEntry;
use App\Feature\Backtest\Request\RunSpecificPairTestRequest;
use App\Feature\Backtest\Request\RunWorkbenchBacktestRequest;
use App\Feature\Backtest\Resource\SpecificPairTestResultResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class BacktestController
{
    public function runSpecificPairTest(
        RunSpecificPairTestRequest $request,
        RunSpecificPairBacktestAction $runSpecificPairBacktest,
    ): AnonymousResourceCollection {
        set_time_limit(0);

        $backtest = $runSpecificPairBacktest->execute(
            $request->toConfig(),
        );

        return SpecificPairTestResultResource::collection(
            $backtest->decisions->toArray(),
        )->additional([
            'order_history' => $backtest->orders
                ->map(static fn (BacktestOrderHistoryEntry $order): array => $order->jsonSerialize())
                ->toArray(),
            'initial_capital' => $backtest->initialCapital,
            'ending_capital' => $backtest->endingCapital,
            'quantity_unit' => $backtest->quantityUnit,
            'units_per_lot' => $backtest->unitsPerLot,
            'quantity_multiplier' => $backtest->quantityMultiplier,
            'execution_config' => $backtest->executionConfiguration->toArray(),
        ]);
    }

    public function createTest(LoadBacktestSetupPageAction $loadPage): View
    {
        return view('workbench.tests.create', $loadPage->execute());
    }

    public function runTest(
        RunWorkbenchBacktestRequest $request,
        RunConfiguredPairBacktestAction $runBacktest,
        InitiateMarketTypeBacktestAction $initiateMarketTypeBacktest,
    ): RedirectResponse {
        if ($request->validated('test_type') === 'market_type') {
            $run = $initiateMarketTypeBacktest->execute($request->validated());

            return redirect()->route('workbench.tests.market-type-runs.show', $run)
                ->with('status', 'Parallel backtest queued for '.$run->total_pairs.' market pair(s).');
        }

        set_time_limit(0);

        $request->session()->put(
            'backtestResult',
            $runBacktest->execute($request->toConfiguredPairBacktestInput())->toPersistedArray(),
        );

        return redirect()->route('workbench.tests.results');
    }

    public function marketTypeRun(int $marketTypeBacktestRun, LoadMarketTypeBacktestRunAction $loadRun): View
    {
        return view('workbench.tests.market-type-run', $loadRun->execute($marketTypeBacktestRun));
    }
}
