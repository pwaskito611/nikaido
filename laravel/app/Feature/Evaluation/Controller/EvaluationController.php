<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Controller;

use App\Feature\Evaluation\Action\LoadMarketTypeOverallEvaluationAction;
use App\Feature\Evaluation\Action\LoadMarketTypePairEvaluationAction;
use App\Feature\Evaluation\Action\PrepareBacktestEvaluationPageAction;
use App\Feature\Evaluation\Request\BacktestResultsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EvaluationController
{
    public function marketTypePairResult(
        int $marketTypeBacktestRun,
        int $pairRun,
        BacktestResultsRequest $request,
        LoadMarketTypePairEvaluationAction $loadEvaluation,
        PrepareBacktestEvaluationPageAction $preparePage,
    ): View {
        $result = $loadEvaluation->execute($marketTypeBacktestRun, $pairRun);

        return view('workbench.tests.results', $preparePage->execute(
            $request,
            $result,
            route('workbench.tests.market-type-runs.pairs.show', [$marketTypeBacktestRun, $pairRun]),
            'Pair backtest results',
            'MARKET-TYPE BACKTEST / RUN #'.$marketTypeBacktestRun.' / PAIR ANALYSIS',
            route('workbench.tests.market-type-runs.show', $marketTypeBacktestRun),
        ));
    }

    public function marketTypeOverallResult(
        int $marketTypeBacktestRun,
        BacktestResultsRequest $request,
        LoadMarketTypeOverallEvaluationAction $loadEvaluation,
        PrepareBacktestEvaluationPageAction $preparePage,
    ): View {
        $overall = $loadEvaluation->execute($marketTypeBacktestRun);

        return view('workbench.tests.results', $preparePage->execute(
            $request,
            $overall['result'],
            route('workbench.tests.market-type-runs.overall', $marketTypeBacktestRun),
            'Overall market-type results',
            'MARKET-TYPE BACKTEST / RUN #'.$marketTypeBacktestRun.' / OVERALL RESULT',
            route('workbench.tests.market-type-runs.show', $marketTypeBacktestRun),
            $overall['successfulPairCount'],
        ));
    }

    public function results(
        BacktestResultsRequest $request,
        PrepareBacktestEvaluationPageAction $preparePage,
    ): View|RedirectResponse {
        $result = $request->session()->get('backtestResult');
        if (! is_array($result)) {
            return redirect()->route('workbench.tests.create')
                ->with('status', 'Run a test first to view its results.');
        }

        return view('workbench.tests.results', $preparePage->execute(
            $request,
            $result,
            route('workbench.tests.results'),
            'Test results',
            'RESEARCH / BACKTEST COMPLETE',
            route('workbench.tests.create'),
        ));
    }
}
