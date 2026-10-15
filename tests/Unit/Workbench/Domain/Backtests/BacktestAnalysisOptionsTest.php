<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\Backtests;

use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOptions;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class BacktestAnalysisOptionsTest extends TestCase
{
    public function test_it_converts_validated_query_values_to_typed_options(): void
    {
        $options = BacktestAnalysisOptions::fromValidatedQuery([
            'stress_x' => '0.5',
            'monte_carlo' => '1',
            'simulation_count' => '20',
            'trades_per_simulation' => '2',
            'method' => 'bootstrap',
            'mc_initial_capital' => '10000',
            'random_seed' => '42',
            'risk_model' => 'fixed_monetary_pl',
            'drawdown_threshold' => '10',
        ], 3, 2);

        $this->assertTrue($options->stressParameter->contains(0.5));
        $this->assertTrue($options->monteCarloConfig->exists(
            static fn (MonteCarloSimulationConfig $config): bool => $config->simulationCount === 20
                && $config->tradesPerSimulation === 2
                && $config->randomSeed === 42,
        ));
        $this->assertTrue($options->clusterCount->contains(3));
        $this->assertSame(2, $options->selectedCluster);
    }

    public function test_absent_analysis_options_use_exact_none(): void
    {
        $options = BacktestAnalysisOptions::fromValidatedQuery([], null, 1);

        $this->assertTrue($options->stressParameter->isNone());
        $this->assertTrue($options->monteCarloConfig->isNone());
        $this->assertTrue($options->clusterCount->isNone());
    }

    public function test_it_rejects_an_out_of_range_selected_cluster(): void
    {
        $this->expectException(UnexpectedValueException::class);

        BacktestAnalysisOptions::fromValidatedQuery([], 2, 3);
    }

    public function test_it_rejects_incomplete_monte_carlo_configuration(): void
    {
        $this->expectException(UnexpectedValueException::class);

        BacktestAnalysisOptions::fromValidatedQuery([
            'monte_carlo' => '1',
            'simulation_count' => 10,
        ], null, 1);
    }
}
