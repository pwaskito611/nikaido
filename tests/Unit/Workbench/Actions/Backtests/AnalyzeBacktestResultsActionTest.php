<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Actions\Backtests;

use App\Engine\Actions\RunFluctuationStressTestAction;
use App\Engine\Actions\RunMonteCarloSimulationAction;
use App\Engine\Config\MonteCarloSimulationConfig;
use App\Workbench\Actions\Backtests\AnalyzeBacktestResultsAction;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnalyzeBacktestResultsActionTest extends TestCase
{
    public function test_it_selects_decisions_and_orders_for_requested_cluster(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new RunFluctuationStressTestAction,
            new RunMonteCarloSimulationAction,
        );

        $analysis = $action->execute(
            $this->backtest(),
            null,
            0.5,
            0.5,
            null,
            2,
            2,
        );

        $this->assertSame(2, $analysis->clusterCount);
        $this->assertSame(2, $analysis->selectedCluster);
        $this->assertSame([
            ['candle' => ['price_date' => '2026-10-12 10:02:00']],
            ['candle' => ['price_date' => '2026-10-12 10:03:00']],
        ], $analysis->decisions);
        $this->assertSame([], $analysis->orders);
        $this->assertSame(10000.0, $analysis->clusterAnalysis['capital_start']);
        $this->assertSame('24.00,12.00 616.00,12.00', $analysis->capitalChart['points']);
    }

    public function test_it_rejects_monte_carlo_when_no_closed_trade_is_available(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new RunFluctuationStressTestAction,
            new RunMonteCarloSimulationAction,
        );

        $this->expectException(ValidationException::class);
        $action->execute(
            $this->backtest(),
            null,
            0.5,
            0.5,
            new MonteCarloSimulationConfig(simulationCount: 10),
            null,
            1,
        );
    }

    public function test_it_includes_realized_partial_exit_fills_in_pnl_and_capital_movement(): void
    {
        $action = new AnalyzeBacktestResultsAction(
            new RunFluctuationStressTestAction,
            new RunMonteCarloSimulationAction,
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

        $analysis = $action->execute($backtest, null, 0.5, 0.5, null, null, 1);

        $this->assertSame(-5.0, $analysis->clusterAnalysis['net_profit_loss']);
        $this->assertSame(9995.0, $analysis->clusterAnalysis['capital_end']);
        $this->assertSame(9995.0, $analysis->capitalChart['end']);
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
