<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Actions\Backtests;

use App\Feature\Backtest\Action\AnalyzeBacktestResultsAction;
use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisDecision;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisInput;
use App\Feature\Backtest\Data\Analysis\BacktestAnalysisOptions;
use App\Feature\Backtest\Process\BacktestClusterAnalysisProcess;
use App\Feature\Backtest\Process\FluctuationStressTestProcess;
use App\Feature\Backtest\Process\MonteCarloSimulationProcess;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnalyzeBacktestResultsActionTest extends TestCase
{
    public function test_it_selects_decisions_and_orders_for_requested_cluster(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new FluctuationStressTestProcess,
            new MonteCarloSimulationProcess,
            new BacktestClusterAnalysisProcess,
        );

        $analysis = $action->execute(
            BacktestAnalysisInput::fromPersistedResult($this->backtest()),
            BacktestAnalysisOptions::fromValidatedQuery([], 2, 2),
        );

        $this->assertSame(2, $analysis->clusterCount->getOrNull());
        $this->assertSame(2, $analysis->selectedCluster->getOrNull());
        $this->assertSame([
            ['candle' => ['price_date' => '2026-10-12 10:02:00']],
            ['candle' => ['price_date' => '2026-10-12 10:03:00']],
        ], $analysis->decisions->map(
            static fn (BacktestAnalysisDecision $decision): array => $decision->toPresentationArray(),
        )->toArray());
        $this->assertTrue($analysis->orders->isEmpty());
        $this->assertSame(10000.0, $analysis->clusterAnalysis->capitalStart);
        $this->assertSame('24.00,12.00 616.00,12.00', $analysis->capitalChart->points);
    }

    public function test_it_rejects_monte_carlo_when_no_closed_trade_is_available(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new FluctuationStressTestProcess,
            new MonteCarloSimulationProcess,
            new BacktestClusterAnalysisProcess,
        );

        $this->expectException(ValidationException::class);
        $action->execute(
            BacktestAnalysisInput::fromPersistedResult($this->backtest()),
            BacktestAnalysisOptions::fromValidatedQuery([
                'monte_carlo' => '1',
                'simulation_count' => 10,
                'method' => MonteCarloSimulationConfig::METHOD_SHUFFLE,
                'mc_initial_capital' => 10000,
                'risk_model' => MonteCarloSimulationConfig::RISK_MODEL_FIXED_MONETARY_PL,
                'drawdown_threshold' => 20,
            ], null, 1),
        );
    }

    public function test_it_includes_realized_partial_exit_fills_in_pnl_and_capital_movement(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new FluctuationStressTestProcess,
            new MonteCarloSimulationProcess,
            new BacktestClusterAnalysisProcess,
        );
        $backtest = $this->backtest();
        $backtest['order_history'] = [[
            'id' => 1,
            'signal' => 'long',
            'open_date' => '2026-10-12 10:00:00',
            'close_date' => null,
            'profit_loss' => null,
            'realized_profit_loss' => -5.0,
            'capital_after_close' => null,
            'exit_fills' => [[
                'date' => '2026-10-12 10:01:00',
                'profit_loss' => -5.0,
                'capital_after' => 9995.0,
            ]],
        ]];

        $analysis = $action->execute(
            BacktestAnalysisInput::fromPersistedResult($backtest),
            BacktestAnalysisOptions::fromValidatedQuery([], null, 1),
        );

        $this->assertSame(-5.0, $analysis->clusterAnalysis->netProfitLoss);
        $this->assertSame(9995.0, $analysis->clusterAnalysis->capitalEnd);
        $this->assertSame(9995.0, $analysis->capitalChart->end);
    }

    public function test_it_calculates_profit_factor_and_drawdown_percentages_from_full_equity_history(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new FluctuationStressTestProcess,
            new MonteCarloSimulationProcess,
            new BacktestClusterAnalysisProcess,
        );
        $backtest = $this->backtest();
        $backtest['initial_capital'] = 1000.0;
        $backtest['order_history'] = [];

        foreach ([
            ['profit_loss' => 100.0, 'capital' => 1100.0],
            ['profit_loss' => -55.0, 'capital' => 1045.0],
            ['profit_loss' => -110.0, 'capital' => 935.0],
            ['profit_loss' => 55.0, 'capital' => 990.0],
        ] as $index => $trade) {
            $date = sprintf('2026-10-12 10:%02d:00', $index + 1);
            $backtest['order_history'][] = [
                'id' => $index + 1,
                'signal' => $trade['profit_loss'] > 0 ? 'long' : 'short',
                'open_date' => sprintf('2026-10-12 09:%02d:00', $index + 1),
                'close_date' => $date,
                'profit_loss' => $trade['profit_loss'],
                'realized_profit_loss' => $trade['profit_loss'],
                'capital_at_open' => 1000.0,
                'capital_after_close' => $trade['capital'],
                'exit_fills' => [[
                    'date' => $date,
                    'profit_loss' => $trade['profit_loss'],
                    'capital_after' => $trade['capital'],
                ]],
            ];
        }

        $analysis = $action->execute(
            BacktestAnalysisInput::fromPersistedResult($backtest),
            BacktestAnalysisOptions::fromValidatedQuery([], null, 1),
        );

        $this->assertSame(4, $analysis->closedTradeCount);
        $this->assertSame(155 / 165, $analysis->profitFactor);
        $this->assertEqualsWithDelta(10.0, $analysis->averageDrawdownPercent, 0.000001);
        $this->assertEqualsWithDelta(15.0, $analysis->maximumDrawdownPercent, 0.000001);
        $this->assertEqualsWithDelta(10.0, $analysis->medianDrawdownPercent, 0.000001);
    }

    /**
     * @return array<string, mixed>
     */
    private function backtest(): array
    {
        return [
            'initial_capital' => 10000.0,
            'results' => [
                ['candle' => ['price_date' => '2026-10-12 10:00:00']],
                ['candle' => ['price_date' => '2026-10-12 10:01:00']],
                ['candle' => ['price_date' => '2026-10-12 10:02:00']],
                ['candle' => ['price_date' => '2026-10-12 10:03:00']],
            ],
            'order_history' => [],
        ];
    }
}
