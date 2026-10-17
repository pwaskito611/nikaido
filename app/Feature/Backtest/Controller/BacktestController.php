<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Controller;

use App\Feature\Backtest\Action\AnalyzeBacktestResultsAction;
use App\Feature\Backtest\Action\InitiateMarketTypeBacktestAction;
use App\Feature\Backtest\Action\LoadBacktestSetupPageAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\CombineMarketTypeBacktestResultsAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypeBacktestRunAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypeOverallResultAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypePairResultAction;
use App\Feature\Backtest\Action\RunConfiguredPairBacktestAction;
use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisDecision;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOrder;
use App\Feature\Backtest\Data\BacktestOrderHistoryEntry;
use App\Feature\Backtest\Data\BacktestResultsAnalysis;
use App\Feature\Backtest\Data\FluctuationStressTestResult;
use App\Feature\Backtest\Data\MonteCarloSimulationResult;
use App\Feature\Backtest\Request\BacktestResultsRequest;
use App\Feature\Backtest\Request\RunSpecificPairTestRequest;
use App\Feature\Backtest\Request\RunWorkbenchBacktestRequest;
use App\Feature\Backtest\Resource\SpecificPairTestResultResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function marketTypePairResult(
        int $marketTypeBacktestRun,
        int $pairRun,
        BacktestResultsRequest $request,
        LoadMarketTypePairResultAction $loadPairResult,
        AnalyzeBacktestResultsAction $analyze,
    ): View|RedirectResponse {
        $pair = $loadPairResult->execute($marketTypeBacktestRun, $pairRun);

        return $this->resultsView(
            $request,
            $analyze,
            $pair->result,
            route('workbench.tests.market-type-runs.pairs.show', [$marketTypeBacktestRun, $pairRun]),
            'Pair backtest results',
            'MARKET-TYPE BACKTEST / RUN #'.$marketTypeBacktestRun.' / PAIR ANALYSIS',
            route('workbench.tests.market-type-runs.show', $marketTypeBacktestRun),
        );
    }

    public function marketTypeOverallResult(
        int $marketTypeBacktestRun,
        BacktestResultsRequest $request,
        LoadMarketTypeOverallResultAction $loadOverall,
        CombineMarketTypeBacktestResultsAction $combineResults,
        AnalyzeBacktestResultsAction $analyze,
    ): View|RedirectResponse {
        $overall = $loadOverall->execute($marketTypeBacktestRun);
        $result = $combineResults->execute($overall['run'], $overall['pairRuns']->all());

        return $this->resultsView(
            $request,
            $analyze,
            $result,
            route('workbench.tests.market-type-runs.overall', $marketTypeBacktestRun),
            'Overall market-type results',
            'MARKET-TYPE BACKTEST / RUN #'.$marketTypeBacktestRun.' / OVERALL RESULT',
            route('workbench.tests.market-type-runs.show', $marketTypeBacktestRun),
            $overall['pairRuns']->count(),
        );
    }

    public function results(BacktestResultsRequest $request, AnalyzeBacktestResultsAction $analyze): View|RedirectResponse
    {
        $result = $request->session()->get('backtestResult');
        if (! is_array($result)) {
            return redirect()->route('workbench.tests.create')
                ->with('status', 'Run a test first to view its results.');
        }

        return $this->resultsView(
            $request,
            $analyze,
            $result,
            route('workbench.tests.results'),
            'Test results',
            'RESEARCH / BACKTEST COMPLETE',
            route('workbench.tests.create'),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function resultsView(
        BacktestResultsRequest $request,
        AnalyzeBacktestResultsAction $analyze,
        array $result,
        string $analysisAction,
        string $resultsTitle,
        string $resultsEyebrow,
        string $returnUrl,
        ?int $successfulPairCount = null,
    ): View {
        $options = $request->toAnalysisOptions();
        $analysis = $analyze->execute(
            BacktestAnalysisInput::fromPersistedResult($result, $options->stressParameter->isSome()),
            $options,
        );

        $clusterCount = $analysis->clusterCount->getOrNull();

        return view('workbench.tests.results', [
            'backtest' => $result,
            'orders' => $analysis->orders
                ->map(static fn (BacktestAnalysisOrder $order): array => $order->toPresentationArray())
                ->toArray(),
            'decisions' => $this->paginateDecisions($request, $analysis),
            'clusterCount' => $clusterCount,
            'selectedCluster' => $analysis->selectedCluster->getOrNull(),
            'clusterOptions' => $clusterCount === null ? [] : range(1, $clusterCount),
            'clusterAnalysis' => $analysis->clusterAnalysis->toArray(),
            'capitalChart' => $analysis->capitalChart->toArray(),
            'profitFactor' => $analysis->profitFactor,
            'closedTradeCount' => $analysis->closedTradeCount,
            'averageDrawdownPercent' => $analysis->averageDrawdownPercent,
            'maximumDrawdownPercent' => $analysis->maximumDrawdownPercent,
            'medianDrawdownPercent' => $analysis->medianDrawdownPercent,
            'stressTest' => $analysis->stressTest->map(
                static fn (FluctuationStressTestResult $stressTest): array => $stressTest->toArray(),
            )->getOrNull(),
            'monteCarlo' => $analysis->monteCarlo->map(
                static fn (MonteCarloSimulationResult $monteCarlo): array => $monteCarlo->toArray(),
            )->getOrNull(),
            'analysisAction' => $analysisAction,
            'resultsTitle' => $resultsTitle,
            'resultsEyebrow' => $resultsEyebrow,
            'returnUrl' => $returnUrl,
            'successfulPairCount' => $successfulPairCount,
        ]);
    }

    private function paginateDecisions(BacktestResultsRequest $request, BacktestResultsAnalysis $analysis): LengthAwarePaginator
    {
        $page = (int) ($request->validated('page') ?? 1);
        $decisions = $analysis->decisions
            ->map(static fn (BacktestAnalysisDecision $decision): array => $decision->toPresentationArray())
            ->toArray();

        return new LengthAwarePaginator(
            array_slice($decisions, ($page - 1) * 50, 50),
            count($decisions),
            50,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
