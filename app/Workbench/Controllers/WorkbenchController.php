<?php

declare(strict_types=1);

namespace App\Workbench\Controllers;

use App\Workbench\Actions\MarketPairs\ListAllMarketPairsAction;
use App\Workbench\Actions\RunWorkbenchBacktestAction;
use App\Workbench\Actions\TimeframeTypes\ListAllTimeframeTypesAction;
use App\Workbench\Requests\RunWorkbenchBacktestRequest;
use Illuminate\Http\Request;
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

    public function results(Request $request): View|RedirectResponse
    {
        $result = session('backtestResult');

        if (! is_array($result)) {
            return redirect()->route('workbench.tests.create')
                ->with('status', 'Jalankan test terlebih dahulu untuk melihat hasilnya.');
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
        ]);
    }
}
