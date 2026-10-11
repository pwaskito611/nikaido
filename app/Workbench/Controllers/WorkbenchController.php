<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Workbench\Actions\MarketPairs\ListAllMarketPairsAction;
use App\Workbench\Actions\RunFluctuationStressTestAction;
use App\Workbench\Actions\RunMonteCarloSimulationAction;
use App\Workbench\Actions\RunWorkbenchBacktestAction;
use App\Workbench\Actions\TimeframeTypes\ListAllTimeframeTypesAction;
use App\Workbench\Config\MonteCarloSimulationConfig;
use App\Workbench\Requests\RunWorkbenchBacktestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkbenchController
{
    public function index(): View
    {
        return view('workbench.index');
    }

    public function createTest(
        ListAllMarketPairsAction $marketPairs,
        ListAllTimeframeTypesAction $timeframes,
    ): View {
        return view('workbench.tests.create', [
            'marketPairs' => $marketPairs->execute(),
            'timeframes' => $timeframes->execute(),
        ]);
    }

    public function runTest(
        RunWorkbenchBacktestRequest $request,
        RunWorkbenchBacktestAction $runBacktest,
    ): RedirectResponse {
        $request->session()->put('backtestResult', $runBacktest->execute($request->validated()));

        return redirect()->route('workbench.tests.results');
    }

    public function results(
        Request $request,
        RunFluctuationStressTestAction $runStressTest,
        RunMonteCarloSimulationAction $runMonteCarlo,
    ): View|RedirectResponse {
        $result = session('backtestResult');

        if (! is_array($result)) {
            return redirect()->route('workbench.tests.create')
                ->with('status', 'Jalankan test terlebih dahulu untuk melihat hasilnya.');
        }

        $request->validate([
            'stress_mode' => ['nullable', 'in:adverse,favorable,combined'],
            'adverse_stress' => ['required_with:stress_mode', 'numeric', 'between:0,1'],
            'favorable_stress' => ['required_with:stress_mode', 'numeric', 'between:0,1'],
        ]);
        $stressTest = null;
        if ($request->filled('stress_mode')) {
            $stressTest = $runStressTest->execute(
                $result,
                (string) $request->query('stress_mode'),
                (float) $request->query('adverse_stress', 0.5),
                (float) $request->query('favorable_stress', 0.5),
            );
        }

        $monteCarlo = null;
        if ($request->boolean('monte_carlo')) {
            $validatedMonteCarlo = $request->validate([
                'monte_carlo' => ['accepted'],
                'simulation_count' => ['required', 'integer', 'between:1,10000'],
                'trades_per_simulation' => ['nullable', 'integer', 'between:1,10000'],
                'method' => ['required', 'in:shuffle,bootstrap'],
                'mc_initial_capital' => ['required', 'numeric', 'gt:0'],
                'random_seed' => ['nullable', 'integer', 'between:0,2147483647'],
                'risk_model' => ['required', 'in:fixed_monetary_pl,compounded_return'],
                'drawdown_threshold' => ['required', 'numeric', 'between:0,100'],
            ]);

            $closedTrades = array_values(array_filter(
                $result['order_history'],
                static fn (array $order): bool => $order['close_date'] !== null,
            ));
            if ($closedTrades === []) {
                throw ValidationException::withMessages([
                    'monte_carlo' => 'Monte Carlo requires at least one closed trade with a numeric P/L.',
                ]);
            }
            if (count(array_filter(
                $closedTrades,
                static fn (array $order): bool => ! is_numeric($order['profit_loss'] ?? null),
            )) > 0) {
                throw ValidationException::withMessages([
                    'monte_carlo' => 'Every closed trade must have a numeric P/L for Monte Carlo simulation.',
                ]);
            }
            if ($validatedMonteCarlo['risk_model'] === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
                && count(array_filter(
                    $closedTrades,
                    static fn (array $order): bool => ! is_numeric($order['capital_at_open'] ?? null)
                        || (float) $order['capital_at_open'] <= 0
                        || ! is_finite((float) $order['capital_at_open']),
                )) > 0) {
                throw ValidationException::withMessages([
                    'monte_carlo' => 'Compounded return requires a positive opening capital for every closed trade.',
                ]);
            }
            if ($validatedMonteCarlo['method'] === MonteCarloSimulationConfig::METHOD_SHUFFLE
                && isset($validatedMonteCarlo['trades_per_simulation'])
                && (int) $validatedMonteCarlo['trades_per_simulation'] !== count($closedTrades)) {
                throw ValidationException::withMessages([
                    'trades_per_simulation' => 'Shuffle must use every historical closed trade exactly once.',
                ]);
            }

            $monteCarloConfig = new MonteCarloSimulationConfig(
                simulationCount: (int) $validatedMonteCarlo['simulation_count'],
                tradesPerSimulation: isset($validatedMonteCarlo['trades_per_simulation'])
                    ? (int) $validatedMonteCarlo['trades_per_simulation']
                    : null,
                method: $validatedMonteCarlo['method'],
                initialCapital: (float) $validatedMonteCarlo['mc_initial_capital'],
                randomSeed: isset($validatedMonteCarlo['random_seed'])
                    ? (int) $validatedMonteCarlo['random_seed']
                    : null,
                riskModel: $validatedMonteCarlo['risk_model'],
                drawdownThreshold: (float) $validatedMonteCarlo['drawdown_threshold'],
            );
            $tradesPerSimulation = $monteCarloConfig->tradesPerSimulation ?? count($closedTrades);
            if ($monteCarloConfig->simulationCount * $tradesPerSimulation > MonteCarloSimulationConfig::MAX_SIMULATED_TRADES) {
                throw ValidationException::withMessages([
                    'simulation_count' => 'The requested simulation workload exceeds the supported limit.',
                ]);
            }

            $monteCarlo = $runMonteCarlo->execute($result, $monteCarloConfig);
        }

        $requestedClusterCount = $request->query('clusters', 'full');
        $clusterCount = $requestedClusterCount === 'full'
            ? null
            : filter_var($requestedClusterCount, FILTER_VALIDATE_INT);
        if ($clusterCount !== null && ($clusterCount === false || $clusterCount < 2 || $clusterCount > 10)) {
            $clusterCount = null;
        }

        $results = $result['results'];
        $selectedCluster = null;
        $clusterResults = $results;
        if ($clusterCount !== null) {
            $selectedCluster = filter_var($request->query('cluster', 1), FILTER_VALIDATE_INT);
            if ($selectedCluster === false || $selectedCluster < 1 || $selectedCluster > $clusterCount) {
                $selectedCluster = 1;
            }

            $totalResults = count($results);
            $start = (int) floor(($selectedCluster - 1) * $totalResults / $clusterCount);
            $end = (int) floor($selectedCluster * $totalResults / $clusterCount);
            $clusterResults = array_slice($results, $start, $end - $start);
        }

        $orders = $result['order_history'];
        if ($clusterCount !== null) {
            $clusterTimestamps = array_column(array_column($clusterResults, 'candle'), 'price_date');
            if ($clusterTimestamps === []) {
                $orders = [];
            } else {
                $firstTimestamp = strtotime(min($clusterTimestamps));
                $lastTimestamp = strtotime(max($clusterTimestamps));
                $orders = array_values(array_filter(
                    $orders,
                    static function (array $order) use ($firstTimestamp, $lastTimestamp): bool {
                        $openTimestamp = strtotime($order['open_date']);

                        return $openTimestamp !== false
                            && $openTimestamp >= $firstTimestamp
                            && $openTimestamp <= $lastTimestamp;
                    },
                ));
            }
        }

        $closedOrders = array_values(array_filter(
            $orders,
            static fn (array $order): bool => $order['close_date'] !== null,
        ));
        $winningOrders = array_filter(
            $closedOrders,
            static fn (array $order): bool => $order['profit_loss'] > 0,
        );
        $losingOrders = array_filter(
            $closedOrders,
            static fn (array $order): bool => $order['profit_loss'] < 0,
        );

        $initialCapital = (float) $result['initial_capital'];
        $capitalStart = $initialCapital;
        if ($clusterCount !== null && $clusterResults !== []) {
            $clusterStartDate = $clusterResults[0]['candle']['price_date'];
            foreach ($result['order_history'] as $order) {
                if ($order['close_date'] !== null && $order['close_date'] < $clusterStartDate) {
                    $capitalStart = (float) $order['capital_after_close'];
                }
            }
        }

        $capitalSeries = [['date' => null, 'capital' => $capitalStart]];
        foreach ($closedOrders as $order) {
            $capitalSeries[] = [
                'date' => $order['close_date'],
                'capital' => (float) $order['capital_after_close'],
            ];
        }
        $capitalValues = array_column($capitalSeries, 'capital');
        $capitalMinimum = min($capitalValues);
        $capitalMaximum = max($capitalValues);
        $capitalRange = max($capitalMaximum - $capitalMinimum, 1.0);
        $lastChartIndex = max(count($capitalSeries) - 1, 1);
        $chartCoordinates = [];
        foreach ($capitalSeries as $index => $point) {
            $x = 24 + 592 * $index / $lastChartIndex;
            $y = 12 + 150 * ($capitalMaximum - $point['capital']) / $capitalRange;
            $chartCoordinates[] = number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        }
        if (count($chartCoordinates) === 1) {
            $chartCoordinates[] = '616.00,'.substr($chartCoordinates[0], strpos($chartCoordinates[0], ',') + 1);
        }

        $currentPage = max($request->integer('page', 1), 1);
        $decisions = new LengthAwarePaginator(
            array_slice($clusterResults, ($currentPage - 1) * 50, 50),
            count($clusterResults),
            50,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('workbench.tests.results', [
            'backtest' => $result,
            'orders' => $orders,
            'decisions' => $decisions,
            'clusterCount' => $clusterCount,
            'selectedCluster' => $selectedCluster,
            'clusterOptions' => $clusterCount === null ? [] : range(1, $clusterCount),
            'clusterAnalysis' => [
                'orders' => count($orders),
                'closed' => count($closedOrders),
                'wins' => count($winningOrders),
                'losses' => count($losingOrders),
                'net_profit_loss' => array_sum(array_column($closedOrders, 'profit_loss')),
                'win_rate' => count($closedOrders) > 0 ? count($winningOrders) / count($closedOrders) * 100 : 0,
                'capital_start' => $capitalStart,
                'capital_end' => $capitalSeries[array_key_last($capitalSeries)]['capital'],
            ],
            'capitalChart' => [
                'points' => implode(' ', $chartCoordinates),
                'minimum' => $capitalMinimum,
                'maximum' => $capitalMaximum,
                'start' => $capitalStart,
                'end' => $capitalSeries[array_key_last($capitalSeries)]['capital'],
            ],
            'stressTest' => $stressTest,
            'monteCarlo' => $monteCarlo,
        ]);
    }
}
