<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Engine\Actions\InitiateMarketTypeBacktestAction;
use App\Engine\Actions\RunConfiguredPairBacktestAction;
use App\Engine\Config\MonteCarloSimulationConfig;
use App\Workbench\Actions\Backtests\AnalyzeBacktestResultsAction;
use App\Workbench\Actions\MarketPairs\ListAllMarketPairsAction;
use App\Workbench\Actions\MarketTypeBacktests\CombineMarketTypeBacktestResultsAction;
use App\Workbench\Actions\MarketTypeBacktests\LoadMarketTypeBacktestRunAction;
use App\Workbench\Actions\MarketTypeBacktests\LoadMarketTypeOverallResultAction;
use App\Workbench\Actions\MarketTypeBacktests\LoadMarketTypePairResultAction;
use App\Workbench\Actions\MarketTypes\ListAllMarketTypesAction;
use App\Workbench\Actions\TimeframeTypes\ListAllTimeframeTypesAction;
use App\Workbench\Models\BacktestResultsAnalysis;
use App\Workbench\Requests\BacktestResultsRequest;
use App\Workbench\Requests\RunWorkbenchBacktestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WorkbenchController
{
    public function index(): View
    {
        return view('workbench.index');
    }

    public function createTest(
        ListAllMarketPairsAction $marketPairs,
        ListAllMarketTypesAction $marketTypes,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        return view('workbench.tests.create', [
            'marketPairs' => $marketPairs->execute(),
            'marketTypes' => $marketTypes->execute(),
            'timeframes' => $timeframes->execute(),
        ]);
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

        $request->session()->put('backtestResult', $runBacktest->execute($request->validated()));

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
                ->with('status', 'Jalankan test terlebih dahulu untuk melihat hasilnya.');
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
        $input = $request->validated();
        $monteCarloConfig = null;
        if ($request->boolean('monte_carlo')) {
            $monteCarloConfig = new MonteCarloSimulationConfig(
                simulationCount: (int) $input['simulation_count'],
                tradesPerSimulation: isset($input['trades_per_simulation'])
                    ? (int) $input['trades_per_simulation']
                    : null,
                method: $input['method'],
                initialCapital: (float) $input['mc_initial_capital'],
                randomSeed: isset($input['random_seed']) ? (int) $input['random_seed'] : null,
                riskModel: $input['risk_model'],
                drawdownThreshold: (float) $input['drawdown_threshold'],
            );
        }

        $clusterCount = $request->clusterCount();
        $selectedCluster = $clusterCount === null ? 1 : $request->selectedCluster($clusterCount);
        $analysis = $analyze->execute(
            $result,
            $input['stress_mode'] ?? null,
            (float) ($input['adverse_stress'] ?? 0.5),
            (float) ($input['favorable_stress'] ?? 0.5),
            $monteCarloConfig,
            $clusterCount,
            $selectedCluster,
        );

        return view('workbench.tests.results', [
            'backtest' => $result,
            'orders' => $analysis->orders,
            'decisions' => $this->paginateDecisions($request, $analysis),
            'clusterCount' => $analysis->clusterCount,
            'selectedCluster' => $analysis->selectedCluster,
            'clusterOptions' => $analysis->clusterCount === null ? [] : range(1, $analysis->clusterCount),
            'clusterAnalysis' => $analysis->clusterAnalysis,
            'capitalChart' => $analysis->capitalChart,
            'stressTest' => $analysis->stressTest,
            'monteCarlo' => $analysis->monteCarlo,
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

        return new LengthAwarePaginator(
            array_slice($analysis->decisions, ($page - 1) * 50, 50),
            count($analysis->decisions),
            50,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
