<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use App\Engine\Config\MonteCarloSimulationConfig;
use Gauss\Statistics\Statistics;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class RunMonteCarloSimulationAction
{
    /**
     * @param  array{order_history: list<array<string, mixed>>}  $backtest
     * @return array{
     *     config: array{
     *         simulation_count: int,
     *         trades_per_simulation: int,
     *         method: string,
     *         initial_capital: float,
     *         random_seed: ?int,
     *         risk_model: string,
     *         drawdown_threshold: float
     *     },
     *     metrics: array{
     *         final_equity: array{median: float, mean: float, p5: float, p25: float, p75: float, p95: float},
     *         maximum_drawdown_percent: array{median: float, p95: float, worst: float},
     *         maximum_drawdown_amount: array{median: float, p95: float, worst: float},
     *         probability_of_loss: float,
     *         probability_of_drawdown: float
     *     },
     *     distributions: array{
     *         final_equity: list<float>,
     *         maximum_drawdown_percent: list<float>,
     *         maximum_drawdown_amount: list<float>
     *     },
     *     histograms: array{
     *         final_equity: list<array{from: float, to: float, count: int}>,
     *         maximum_drawdown_percent: list<array{from: float, to: float, count: int}>
     *     }
     * }
     */
    public function execute(array $backtest, MonteCarloSimulationConfig $config): array
    {
        $trades = $this->historicalTrades($backtest['order_history'], $config->riskModel);
        if ($trades === []) {
            throw new InvalidArgumentException('Monte Carlo simulation requires at least one closed trade with a numeric P/L.');
        }

        $tradesPerSimulation = $config->tradesPerSimulation ?? count($trades);
        if ($config->method === MonteCarloSimulationConfig::METHOD_SHUFFLE && $tradesPerSimulation !== count($trades)) {
            throw new InvalidArgumentException('Shuffle simulations must use all historical trades exactly once.');
        }
        if ($config->simulationCount * $tradesPerSimulation > MonteCarloSimulationConfig::MAX_SIMULATED_TRADES) {
            throw new InvalidArgumentException('The requested simulation workload exceeds the supported limit.');
        }

        $randomizer = new Randomizer(
            new Mt19937($config->randomSeed ?? random_int(0, 2147483647)),
        );
        $finalEquities = [];
        $drawdownsPercent = [];
        $drawdownsAmount = [];

        for ($simulation = 0; $simulation < $config->simulationCount; $simulation++) {
            $sample = $this->sampleTrades($trades, $tradesPerSimulation, $config->method, $randomizer);
            [$finalEquity, $maximumDrawdownPercent, $maximumDrawdownAmount] = $this->runEquityCurve(
                $sample,
                $config->initialCapital,
                $config->riskModel,
            );
            $finalEquities[] = $finalEquity;
            $drawdownsPercent[] = $maximumDrawdownPercent;
            $drawdownsAmount[] = $maximumDrawdownAmount;
        }

        sort($finalEquities, SORT_NUMERIC);
        sort($drawdownsPercent, SORT_NUMERIC);
        sort($drawdownsAmount, SORT_NUMERIC);

        $lossCount = count(array_filter(
            $finalEquities,
            static fn (float $equity): bool => $equity < $config->initialCapital,
        ));
        $drawdownCount = count(array_filter(
            $drawdownsPercent,
            static fn (float $drawdown): bool => $drawdown >= $config->drawdownThreshold,
        ));

        return [
            'config' => [
                'simulation_count' => $config->simulationCount,
                'trades_per_simulation' => $tradesPerSimulation,
                'method' => $config->method,
                'initial_capital' => $config->initialCapital,
                'random_seed' => $config->randomSeed,
                'risk_model' => $config->riskModel,
                'drawdown_threshold' => $config->drawdownThreshold,
            ],
            'metrics' => [
                'final_equity' => [
                    'median' => $this->quantile($finalEquities, 0.5),
                    'mean' => (float) Statistics::mean($finalEquities, precision: false)->value(),
                    'p5' => $this->quantile($finalEquities, 0.05),
                    'p25' => $this->quantile($finalEquities, 0.25),
                    'p75' => $this->quantile($finalEquities, 0.75),
                    'p95' => $this->quantile($finalEquities, 0.95),
                ],
                'maximum_drawdown_percent' => [
                    'median' => $this->quantile($drawdownsPercent, 0.5),
                    'p95' => $this->quantile($drawdownsPercent, 0.95),
                    'worst' => max($drawdownsPercent),
                ],
                'maximum_drawdown_amount' => [
                    'median' => $this->quantile($drawdownsAmount, 0.5),
                    'p95' => $this->quantile($drawdownsAmount, 0.95),
                    'worst' => max($drawdownsAmount),
                ],
                'probability_of_loss' => $lossCount / $config->simulationCount * 100,
                'probability_of_drawdown' => $drawdownCount / $config->simulationCount * 100,
            ],
            'distributions' => [
                'final_equity' => $finalEquities,
                'maximum_drawdown_percent' => $drawdownsPercent,
                'maximum_drawdown_amount' => $drawdownsAmount,
            ],
            'histograms' => [
                'final_equity' => $this->histogram($finalEquities),
                'maximum_drawdown_percent' => $this->histogram($drawdownsPercent),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return list<array{pnl: float, return: float}>
     */
    private function historicalTrades(array $orders, string $riskModel): array
    {
        $trades = [];

        foreach ($orders as $order) {
            if (($order['close_date'] ?? null) === null) {
                continue;
            }
            if (! is_numeric($order['profit_loss'] ?? null)) {
                throw new InvalidArgumentException('Every closed trade must have a numeric P/L for Monte Carlo simulation.');
            }

            $profitLoss = (float) $order['profit_loss'];
            if (! is_finite($profitLoss)) {
                throw new InvalidArgumentException('Closed trade P/L values must be finite numbers.');
            }

            $capitalAtOpen = $order['capital_at_open'] ?? null;
            if ($riskModel === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
                && (! is_numeric($capitalAtOpen) || (float) $capitalAtOpen <= 0 || ! is_finite((float) $capitalAtOpen))) {
                throw new InvalidArgumentException('Compounded return requires a positive capital_at_open for every closed trade.');
            }

            $tradeReturn = $riskModel === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
                ? $profitLoss / (float) $capitalAtOpen
                : 0.0;
            if (! is_finite($tradeReturn)) {
                throw new InvalidArgumentException('Compounded trade returns must be finite.');
            }

            $trades[] = [
                'pnl' => $profitLoss,
                'return' => $tradeReturn,
            ];
        }

        return $trades;
    }

    /**
     * @param  list<array{pnl: float, return: float}>  $trades
     * @return list<array{pnl: float, return: float}>
     */
    private function sampleTrades(array $trades, int $tradesPerSimulation, string $method, Randomizer $randomizer): array
    {
        if ($method === MonteCarloSimulationConfig::METHOD_SHUFFLE) {
            for ($index = count($trades) - 1; $index > 0; $index--) {
                $swapIndex = $randomizer->getInt(0, $index);
                [$trades[$index], $trades[$swapIndex]] = [$trades[$swapIndex], $trades[$index]];
            }

            return $trades;
        }

        $sample = [];
        $populationSize = count($trades);
        for ($index = 0; $index < $tradesPerSimulation; $index++) {
            $sample[] = $trades[$randomizer->getInt(0, $populationSize - 1)];
        }

        return $sample;
    }

    /**
     * @param  list<array{pnl: float, return: float}>  $trades
     * @return array{float, float, float}
     */
    private function runEquityCurve(array $trades, float $initialCapital, string $riskModel): array
    {
        $equity = $initialCapital;
        $peakEquity = $initialCapital;
        $maximumDrawdownAmount = 0.0;
        $maximumDrawdownPercent = 0.0;

        foreach ($trades as $trade) {
            $nextEquity = $riskModel === MonteCarloSimulationConfig::RISK_MODEL_FIXED_MONETARY_PL
                ? $equity + $trade['pnl']
                : $equity * (1 + $trade['return']);
            if (! is_finite($nextEquity)) {
                throw new InvalidArgumentException('Equity overflowed during a simulation.');
            }

            if ($nextEquity <= 0) {
                $equity = 0.0;
                $maximumDrawdownAmount = max($maximumDrawdownAmount, $peakEquity);
                $maximumDrawdownPercent = max($maximumDrawdownPercent, 100.0);

                break;
            }

            $equity = $nextEquity;
            $peakEquity = max($peakEquity, $equity);
            $drawdownAmount = $peakEquity - $equity;
            $drawdownPercent = $peakEquity > 0 ? $drawdownAmount / $peakEquity * 100 : 100.0;
            $maximumDrawdownAmount = max($maximumDrawdownAmount, $drawdownAmount);
            $maximumDrawdownPercent = max($maximumDrawdownPercent, $drawdownPercent);
        }

        return [$equity, $maximumDrawdownPercent, $maximumDrawdownAmount];
    }

    /**
     * @param  non-empty-list<float>  $values
     */
    private function quantile(array $values, float $quantile): float
    {
        return (float) Statistics::quantile($values, $quantile, precision: false)->value();
    }

    /**
     * @param  non-empty-list<float>  $values
     * @return list<array{from: float, to: float, count: int}>
     */
    private function histogram(array $values): array
    {
        $minimum = min($values);
        $maximum = max($values);
        $counts = array_fill(0, 10, 0);

        foreach ($values as $value) {
            $index = $maximum === $minimum
                ? 0
                : min((int) floor(($value - $minimum) / ($maximum - $minimum) * 10), 9);
            $counts[$index]++;
        }

        $range = $maximum - $minimum;

        return array_map(
            static fn (int $count, int $index): array => [
                'from' => $minimum + $range * $index / 10,
                'to' => $minimum + $range * ($index + 1) / 10,
                'count' => $count,
            ],
            $counts,
            array_keys($counts),
        );
    }
}
