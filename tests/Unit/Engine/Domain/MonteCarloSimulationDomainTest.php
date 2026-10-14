<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Domain;

use App\Features\Engine\Config\MonteCarloSimulationConfig;
use App\Features\Engine\Domain\MonteCarloSimulationDomain as RunMonteCarloSimulationAction;
use App\Features\Engine\Domain\MonteCarloTrade;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use InvalidArgumentException;
use Tests\TestCase;

class MonteCarloSimulationDomainTest extends TestCase
{
    public function test_shuffle_keeps_historical_sample_size_and_pnl_sum(): void
    {
        $result = $this->simulate(
            [10.0, -105.0],
            new MonteCarloSimulationConfig(
                simulationCount: 20,
                method: MonteCarloSimulationConfig::METHOD_SHUFFLE,
                initialCapital: 100,
                randomSeed: 43,
            ),
        );

        $this->assertCount(20, $result['distributions']['final_equity']);
        $this->assertContains(0.0, $result['distributions']['final_equity']);
        $this->assertContains(5.0, $result['distributions']['final_equity']);
        $this->assertNotContains(120.0, $result['distributions']['final_equity']);
        $this->assertSame(2, $result['config']['trades_per_simulation']);
    }

    public function test_bootstrap_uses_configured_sample_size_and_allows_repeated_trades(): void
    {
        $result = $this->simulate(
            [10.0, -5.0],
            new MonteCarloSimulationConfig(
                simulationCount: 200,
                tradesPerSimulation: 3,
                method: MonteCarloSimulationConfig::METHOD_BOOTSTRAP,
                initialCapital: 100,
                randomSeed: 17,
            ),
        );

        $this->assertSame(3, $result['config']['trades_per_simulation']);
        $this->assertContains(130.0, $result['distributions']['final_equity']);
        $this->assertCount(200, $result['distributions']['maximum_drawdown_percent']);
    }

    public function test_random_seed_reproduces_identical_bootstrap_results(): void
    {
        $config = new MonteCarloSimulationConfig(
            simulationCount: 100,
            tradesPerSimulation: 4,
            method: MonteCarloSimulationConfig::METHOD_BOOTSTRAP,
            initialCapital: 500,
            randomSeed: 123456,
        );

        $first = $this->simulate([15.0, -20.0, 7.5], $config);
        $second = $this->simulate([15.0, -20.0, 7.5], $config);

        $this->assertSame($first, $second);
    }

    public function test_compounded_model_uses_pnl_over_capital_at_open_as_trade_return(): void
    {
        $result = $this->simulate(
            [20.0],
            new MonteCarloSimulationConfig(
                simulationCount: 1,
                initialCapital: 500,
                randomSeed: 1,
                riskModel: MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN,
            ),
        );

        $this->assertSame([600.0], $result['distributions']['final_equity']);
    }

    public function test_drawdown_percentiles_and_probabilities_use_all_simulation_results(): void
    {
        $result = $this->simulate(
            [-25.0],
            new MonteCarloSimulationConfig(
                simulationCount: 20,
                method: MonteCarloSimulationConfig::METHOD_BOOTSTRAP,
                initialCapital: 100,
                randomSeed: 2,
                drawdownThreshold: 25,
            ),
        );

        $this->assertSame(75.0, $result['metrics']['final_equity']['median']);
        $this->assertSame(75.0, $result['metrics']['final_equity']['mean']);
        $this->assertSame(75.0, $result['metrics']['final_equity']['p5']);
        $this->assertSame(75.0, $result['metrics']['final_equity']['p25']);
        $this->assertSame(75.0, $result['metrics']['final_equity']['p75']);
        $this->assertSame(75.0, $result['metrics']['final_equity']['p95']);
        $this->assertSame(25.0, $result['metrics']['maximum_drawdown_percent']['median']);
        $this->assertSame(25.0, $result['metrics']['maximum_drawdown_percent']['p95']);
        $this->assertSame(25.0, $result['metrics']['maximum_drawdown_amount']['worst']);
        $this->assertEquals(100.0, $result['metrics']['probability_of_loss']);
        $this->assertEquals(100.0, $result['metrics']['probability_of_drawdown']);
        $this->assertSame(20, array_sum(array_column($result['histograms']['final_equity'], 'count')));
    }

    public function test_maximum_drawdown_tracks_a_peak_above_initial_equity(): void
    {
        $result = $this->simulate(
            [10.0, -15.0],
            new MonteCarloSimulationConfig(
                simulationCount: 100,
                method: MonteCarloSimulationConfig::METHOD_SHUFFLE,
                initialCapital: 100,
                randomSeed: 44,
            ),
        );

        $this->assertSame(15.0, $result['metrics']['maximum_drawdown_amount']['worst']);
        $this->assertSame(15.0, $result['metrics']['maximum_drawdown_percent']['worst']);
    }

    public function test_zero_and_winning_pl_values_do_not_cause_division_by_zero(): void
    {
        $zero = $this->simulate(
            [0.0],
            new MonteCarloSimulationConfig(simulationCount: 3, initialCapital: 100, randomSeed: 5),
        );
        $winners = $this->simulate(
            [5.0, 10.0],
            new MonteCarloSimulationConfig(simulationCount: 3, initialCapital: 100, randomSeed: 5),
        );

        $this->assertEquals(0.0, $zero['metrics']['probability_of_loss']);
        $this->assertSame(0.0, $zero['metrics']['maximum_drawdown_percent']['worst']);
        $this->assertEquals(0.0, $winners['metrics']['probability_of_loss']);
    }

    public function test_empty_closed_trade_sample_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RunMonteCarloSimulationAction)->execute(
            Vec::empty(),
            new MonteCarloSimulationConfig,
        );
    }

    public function test_shuffle_rejects_a_sample_size_different_from_historical_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->simulate(
            [10.0, -5.0],
            new MonteCarloSimulationConfig(
                tradesPerSimulation: 1,
                method: MonteCarloSimulationConfig::METHOD_SHUFFLE,
            ),
        );
    }

    public function test_compounded_model_requires_a_valid_opening_equity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RunMonteCarloSimulationAction)->execute(
            Vec::of(new MonteCarloTrade(10.0, Option::some(0.0))),
            new MonteCarloSimulationConfig(
                riskModel: MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN,
            ),
        );
    }

    /**
     * @param  list<float>  $profitLosses
     * @return array<string, mixed>
     */
    private function simulate(array $profitLosses, MonteCarloSimulationConfig $config): array
    {
        $trades = Vec::fromArray($profitLosses)->map(
            static fn (float $profitLoss): MonteCarloTrade => new MonteCarloTrade(
                $profitLoss,
                Option::some(100.0),
            ),
        );

        return (new RunMonteCarloSimulationAction)->execute($trades, $config)->toArray();
    }
}
