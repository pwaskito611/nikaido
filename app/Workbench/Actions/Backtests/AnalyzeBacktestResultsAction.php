<?php

declare(strict_types=1);

namespace App\Workbench\Actions\Backtests;

use App\Engine\Actions\RunFluctuationStressTestAction;
use App\Engine\Actions\RunMonteCarloSimulationAction;
use App\Engine\Config\MonteCarloSimulationConfig;
use App\Workbench\Models\BacktestResultsAnalysis;
use Illuminate\Validation\ValidationException;

class AnalyzeBacktestResultsAction
{
    public function __construct(
        private readonly RunFluctuationStressTestAction $runStressTest,
        private readonly RunMonteCarloSimulationAction $runMonteCarlo,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function execute(
        array $result,
        ?string $stressMode,
        float $adverseStress,
        float $favorableStress,
        ?MonteCarloSimulationConfig $monteCarloConfig,
        ?int $clusterCount,
        int $selectedCluster,
    ): BacktestResultsAnalysis {
        $stressTest = $stressMode === null
            ? null
            : $this->runStressTest->execute($result, $stressMode, $adverseStress, $favorableStress);
        $monteCarlo = $monteCarloConfig === null
            ? null
            : $this->runMonteCarloAnalysis($result, $monteCarloConfig);
        $decisions = $this->selectDecisions($result['results'], $clusterCount, $selectedCluster);
        $orders = $this->selectOrders($result['order_history'], $decisions, $clusterCount);
        [$clusterAnalysis, $capitalChart] = $this->summarizeCluster($result, $decisions, $orders, $clusterCount);

        return new BacktestResultsAnalysis(
            decisions: $decisions,
            orders: $orders,
            clusterCount: $clusterCount,
            selectedCluster: $clusterCount === null ? null : $selectedCluster,
            clusterAnalysis: $clusterAnalysis,
            capitalChart: $capitalChart,
            stressTest: $stressTest,
            monteCarlo: $monteCarlo,
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function runMonteCarloAnalysis(array $result, MonteCarloSimulationConfig $config): array
    {
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
        if ($config->riskModel === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
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
        if ($config->method === MonteCarloSimulationConfig::METHOD_SHUFFLE
            && $config->tradesPerSimulation !== null
            && $config->tradesPerSimulation !== count($closedTrades)) {
            throw ValidationException::withMessages([
                'trades_per_simulation' => 'Shuffle must use every historical closed trade exactly once.',
            ]);
        }

        $tradesPerSimulation = $config->tradesPerSimulation ?? count($closedTrades);
        if ($config->simulationCount * $tradesPerSimulation > MonteCarloSimulationConfig::MAX_SIMULATED_TRADES) {
            throw ValidationException::withMessages([
                'simulation_count' => 'The requested simulation workload exceeds the supported limit.',
            ]);
        }

        return $this->runMonteCarlo->execute($result, $config);
    }

    /**
     * @param  list<array<string, mixed>>  $allDecisions
     * @return list<array<string, mixed>>
     */
    private function selectDecisions(array $allDecisions, ?int $clusterCount, int $selectedCluster): array
    {
        if ($clusterCount === null) {
            return $allDecisions;
        }

        $totalDecisions = count($allDecisions);
        $start = (int) floor(($selectedCluster - 1) * $totalDecisions / $clusterCount);
        $end = (int) floor($selectedCluster * $totalDecisions / $clusterCount);

        return array_slice($allDecisions, $start, $end - $start);
    }

    /**
     * @param  list<array<string, mixed>>  $allOrders
     * @param  list<array<string, mixed>>  $decisions
     * @return list<array<string, mixed>>
     */
    private function selectOrders(array $allOrders, array $decisions, ?int $clusterCount): array
    {
        if ($clusterCount === null) {
            return $allOrders;
        }

        $clusterTimestamps = array_column(array_column($decisions, 'candle'), 'price_date');
        if ($clusterTimestamps === []) {
            return [];
        }

        $firstTimestamp = strtotime(min($clusterTimestamps));
        $lastTimestamp = strtotime(max($clusterTimestamps));

        return array_values(array_filter(
            $allOrders,
            static function (array $order) use ($firstTimestamp, $lastTimestamp): bool {
                $openTimestamp = strtotime($order['open_date']);
                if ($openTimestamp !== false && $openTimestamp >= $firstTimestamp && $openTimestamp <= $lastTimestamp) {
                    return true;
                }

                foreach ($order['exit_fills'] ?? [] as $fill) {
                    $fillTimestamp = strtotime($fill['date']);
                    if ($fillTimestamp !== false && $fillTimestamp >= $firstTimestamp && $fillTimestamp <= $lastTimestamp) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $decisions
     * @param  list<array<string, mixed>>  $orders
     * @return array{array<string, int|float>, array{points: string, minimum: float, maximum: float, start: float, end: float}}
     */
    private function summarizeCluster(array $result, array $decisions, array $orders, ?int $clusterCount): array
    {
        $closedOrders = array_values(array_filter(
            $orders,
            static fn (array $order): bool => $order['close_date'] !== null,
        ));
        $winningOrders = array_filter($closedOrders, static fn (array $order): bool => $order['profit_loss'] > 0);
        $losingOrders = array_filter($closedOrders, static fn (array $order): bool => $order['profit_loss'] < 0);
        $capitalStart = $this->clusterStartingCapital($result, $decisions, $clusterCount);
        $exitEvents = [];
        foreach ($orders as $order) {
            foreach ($order['exit_fills'] ?? [] as $fill) {
                if (is_numeric($fill['capital_after'] ?? null)) {
                    $exitEvents[] = [
                        'date' => $fill['date'],
                        'capital' => (float) $fill['capital_after'],
                    ];
                }
            }
            if (($order['exit_fills'] ?? []) === [] && $order['close_date'] !== null) {
                $exitEvents[] = [
                    'date' => $order['close_date'],
                    'capital' => (float) $order['capital_after_close'],
                ];
            }
        }
        usort($exitEvents, static fn (array $left, array $right): int => $left['date'] <=> $right['date']);
        $capitalSeries = [['date' => null, 'capital' => $capitalStart], ...$exitEvents];

        $capitalValues = array_column($capitalSeries, 'capital');
        $capitalMinimum = min($capitalValues);
        $capitalMaximum = max($capitalValues);
        $chartCoordinates = $this->capitalChartCoordinates($capitalSeries, $capitalMinimum, $capitalMaximum);
        $capitalEnd = $capitalSeries[array_key_last($capitalSeries)]['capital'];

        return [
            [
                'orders' => count($orders),
                'closed' => count($closedOrders),
                'wins' => count($winningOrders),
                'losses' => count($losingOrders),
                'net_profit_loss' => array_sum(array_map(
                    static fn (array $order): float => (float) ($order['realized_profit_loss'] ?? $order['profit_loss'] ?? 0),
                    $orders,
                )),
                'win_rate' => count($closedOrders) > 0 ? count($winningOrders) / count($closedOrders) * 100 : 0,
                'capital_start' => $capitalStart,
                'capital_end' => $capitalEnd,
            ],
            [
                'points' => implode(' ', $chartCoordinates),
                'minimum' => $capitalMinimum,
                'maximum' => $capitalMaximum,
                'start' => $capitalStart,
                'end' => $capitalEnd,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $decisions
     */
    private function clusterStartingCapital(array $result, array $decisions, ?int $clusterCount): float
    {
        $capitalStart = (float) $result['initial_capital'];
        if ($clusterCount === null || $decisions === []) {
            return $capitalStart;
        }

        $clusterStartDate = $decisions[0]['candle']['price_date'];
        $priorExitEvents = [];
        foreach ($result['order_history'] as $order) {
            $fills = $order['exit_fills'] ?? [];
            if ($fills !== []) {
                foreach ($fills as $fill) {
                    if (($fill['date'] ?? '') < $clusterStartDate && is_numeric($fill['capital_after'] ?? null)) {
                        $priorExitEvents[] = [
                            'date' => $fill['date'],
                            'capital' => (float) $fill['capital_after'],
                        ];
                    }
                }
            } elseif ($order['close_date'] !== null && $order['close_date'] < $clusterStartDate) {
                $priorExitEvents[] = [
                    'date' => $order['close_date'],
                    'capital' => (float) $order['capital_after_close'],
                ];
            }
        }
        usort($priorExitEvents, static fn (array $left, array $right): int => $left['date'] <=> $right['date']);
        if ($priorExitEvents !== []) {
            $capitalStart = $priorExitEvents[array_key_last($priorExitEvents)]['capital'];
        }

        return $capitalStart;
    }

    /**
     * @param  list<array{date: ?string, capital: float}>  $capitalSeries
     * @return list<string>
     */
    private function capitalChartCoordinates(array $capitalSeries, float $minimum, float $maximum): array
    {
        $capitalRange = max($maximum - $minimum, 1.0);
        $lastChartIndex = max(count($capitalSeries) - 1, 1);
        $coordinates = [];
        foreach ($capitalSeries as $index => $point) {
            $x = 24 + 592 * $index / $lastChartIndex;
            $y = 12 + 150 * ($maximum - $point['capital']) / $capitalRange;
            $coordinates[] = number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        }
        if (count($coordinates) === 1) {
            $coordinates[] = '616.00,'.substr($coordinates[0], strpos($coordinates[0], ',') + 1);
        }

        return $coordinates;
    }
}
