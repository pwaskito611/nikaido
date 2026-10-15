<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Domain;

use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use App\Feature\Backtest\Data\MonteCarloDrawdownMetrics;
use App\Feature\Backtest\Data\MonteCarloEquityMetrics;
use App\Feature\Backtest\Data\MonteCarloHistogramBin;
use App\Feature\Backtest\Data\MonteCarloSimulationMetrics;
use App\Feature\Backtest\Data\MonteCarloSimulationResult;
use Exact\Collection\Vec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MonteCarloResultTypesTest extends TestCase
{
    public function test_equity_metrics_reject_out_of_order_percentiles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonteCarloEquityMetrics(
            median: 50,
            mean: 50,
            p5: 60,
            p25: 40,
            p75: 70,
            p95: 80,
        );
    }

    public function test_drawdown_metrics_reject_negative_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonteCarloDrawdownMetrics(median: -1, p95: 5, worst: 10);
    }

    public function test_simulation_metrics_reject_invalid_probability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonteCarloSimulationMetrics(
            finalEquity: new MonteCarloEquityMetrics(50, 50, 10, 30, 70, 90),
            maximumDrawdownPercent: new MonteCarloDrawdownMetrics(1, 2, 3),
            maximumDrawdownAmount: new MonteCarloDrawdownMetrics(10, 20, 30),
            probabilityOfLoss: 101,
            probabilityOfDrawdown: 0,
        );
    }

    public function test_histogram_bin_rejects_invalid_bounds_and_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MonteCarloHistogramBin(from: 10, to: 5, count: -1);
    }

    public function test_simulation_result_requires_one_value_per_simulation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buildSimulationResult(
            finalEquities: Vec::of(100.0),
            maximumDrawdownsPercent: Vec::of(0.0, 5.0),
            maximumDrawdownsAmount: Vec::of(0.0, 5.0),
        );
    }

    public function test_simulation_result_requires_sorted_distribution_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buildSimulationResult(
            finalEquities: Vec::of(110.0, 100.0),
            maximumDrawdownsPercent: Vec::of(0.0, 5.0),
            maximumDrawdownsAmount: Vec::of(0.0, 5.0),
        );
    }

    public function test_simulation_result_histograms_must_account_for_all_simulations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->buildSimulationResult(
            finalEquityHistogram: Vec::of(new MonteCarloHistogramBin(100.0, 110.0, 1)),
        );
    }

    /**
     * @param  Vec<float>|null  $finalEquities
     * @param  Vec<float>|null  $maximumDrawdownsPercent
     * @param  Vec<float>|null  $maximumDrawdownsAmount
     * @param  Vec<MonteCarloHistogramBin>|null  $finalEquityHistogram
     */
    private function buildSimulationResult(
        ?Vec $finalEquities = null,
        ?Vec $maximumDrawdownsPercent = null,
        ?Vec $maximumDrawdownsAmount = null,
        ?Vec $finalEquityHistogram = null,
    ): MonteCarloSimulationResult {
        $config = new MonteCarloSimulationConfig(simulationCount: 2, initialCapital: 100.0);

        return new MonteCarloSimulationResult(
            config: $config,
            tradesPerSimulation: 1,
            metrics: new MonteCarloSimulationMetrics(
                finalEquity: new MonteCarloEquityMetrics(105.0, 105.0, 100.0, 100.0, 110.0, 110.0),
                maximumDrawdownPercent: new MonteCarloDrawdownMetrics(2.5, 5.0, 5.0),
                maximumDrawdownAmount: new MonteCarloDrawdownMetrics(2.5, 5.0, 5.0),
                probabilityOfLoss: 0.0,
                probabilityOfDrawdown: 50.0,
            ),
            finalEquities: $finalEquities ?? Vec::of(100.0, 110.0),
            maximumDrawdownsPercent: $maximumDrawdownsPercent ?? Vec::of(0.0, 5.0),
            maximumDrawdownsAmount: $maximumDrawdownsAmount ?? Vec::of(0.0, 5.0),
            finalEquityHistogram: $finalEquityHistogram ?? Vec::of(
                new MonteCarloHistogramBin(100.0, 105.0, 1),
                new MonteCarloHistogramBin(105.0, 110.0, 1),
            ),
            maximumDrawdownPercentHistogram: Vec::of(
                new MonteCarloHistogramBin(0.0, 2.5, 1),
                new MonteCarloHistogramBin(2.5, 5.0, 1),
            ),
        );
    }
}
