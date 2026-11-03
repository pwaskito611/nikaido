<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Evaluation;

use App\Feature\Evaluation\Request\BacktestResultsRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BacktestResultsRequestTest extends TestCase
{
    public function test_cluster_query_values_are_bounded_and_invalid_selections_fall_back_to_one(): void
    {
        $request = BacktestResultsRequest::create('/results?clusters=2&cluster=2', 'GET');
        $this->assertSame(2, $request->clusterCount());
        $this->assertSame(2, $request->selectedCluster(2));

        $request = BacktestResultsRequest::create('/results?clusters=10&cluster=11', 'GET');
        $this->assertSame(10, $request->clusterCount());
        $this->assertSame(1, $request->selectedCluster(10));

        foreach (['full', '1', '11', 'invalid'] as $clusters) {
            $request = BacktestResultsRequest::create('/results?clusters='.$clusters, 'GET');
            $this->assertNull($request->clusterCount());
        }

        $request = BacktestResultsRequest::create('/results?cluster=invalid', 'GET');
        $this->assertSame(1, $request->selectedCluster(3));
    }

    public function test_it_accepts_valid_lower_and_upper_query_boundaries(): void
    {
        $query = [
            'stress_x' => '0',
            'page' => '1',
            'monte_carlo' => '1',
            'simulation_count' => '1',
            'trades_per_simulation' => '10000',
            'method' => 'shuffle',
            'mc_initial_capital' => '0.01',
            'random_seed' => '2147483647',
            'risk_model' => 'fixed_monetary_pl',
            'drawdown_threshold' => '100',
        ];
        $request = BacktestResultsRequest::create('/results', 'GET', $query);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidQueryProvider')]
    public function test_it_rejects_invalid_analysis_query_values(string $field, mixed $value): void
    {
        $query = [
            'monte_carlo' => '1',
            'simulation_count' => '10',
            'trades_per_simulation' => '2',
            'method' => 'shuffle',
            'mc_initial_capital' => '1000',
            'random_seed' => '42',
            'risk_model' => 'fixed_monetary_pl',
            'drawdown_threshold' => '20',
            $field => $value,
        ];
        $request = BacktestResultsRequest::create('/results', 'GET', $query);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has($field), $validator->errors()->toJson());
    }

    #[DataProvider('requiredMonteCarloFieldProvider')]
    public function test_it_requires_every_monte_carlo_configuration_field(string $field): void
    {
        $query = [
            'monte_carlo' => '1',
            'simulation_count' => '10',
            'trades_per_simulation' => '2',
            'method' => 'shuffle',
            'mc_initial_capital' => '1000',
            'random_seed' => '42',
            'risk_model' => 'fixed_monetary_pl',
            'drawdown_threshold' => '20',
        ];
        unset($query[$field]);
        $request = BacktestResultsRequest::create('/results', 'GET', $query);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has($field), $validator->errors()->toJson());
    }

    public static function invalidQueryProvider(): array
    {
        return [
            'negative stress parameter' => ['stress_x', '-0.1'],
            'non-numeric stress parameter' => ['stress_x', 'invalid'],
            'page must be a positive integer' => ['page', '0'],
            'page must be an integer' => ['page', '1.5'],
            'simulation count lower boundary' => ['simulation_count', '0'],
            'simulation count upper boundary' => ['simulation_count', '10001'],
            'trades per simulation lower boundary' => ['trades_per_simulation', '0'],
            'trades per simulation upper boundary' => ['trades_per_simulation', '10001'],
            'unsupported method' => ['method', 'invalid'],
            'initial capital must be positive' => ['mc_initial_capital', '0'],
            'random seed upper boundary' => ['random_seed', '2147483648'],
            'random seed lower boundary' => ['random_seed', '-1'],
            'unsupported risk model' => ['risk_model', 'invalid'],
            'drawdown threshold upper boundary' => ['drawdown_threshold', '100.1'],
        ];
    }

    public static function requiredMonteCarloFieldProvider(): array
    {
        return [
            'simulation count' => ['simulation_count'],
            'method' => ['method'],
            'initial capital' => ['mc_initial_capital'],
            'risk model' => ['risk_model'],
            'drawdown threshold' => ['drawdown_threshold'],
        ];
    }
}
