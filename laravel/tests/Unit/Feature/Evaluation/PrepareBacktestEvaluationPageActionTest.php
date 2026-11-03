<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Evaluation;

use App\Feature\Evaluation\Action\AnalyzeBacktestResultsAction;
use App\Feature\Evaluation\Action\PrepareBacktestEvaluationPageAction;
use App\Feature\Evaluation\Process\BacktestClusterAnalysisProcess;
use App\Feature\Evaluation\Process\FluctuationStressTestProcess;
use App\Feature\Evaluation\Process\MonteCarloSimulationProcess;
use App\Feature\Evaluation\Request\BacktestResultsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PrepareBacktestEvaluationPageActionTest extends TestCase
{
    public function test_it_prepares_cost_summaries_and_paginates_decisions(): void
    {
        $action = new PrepareBacktestEvaluationPageAction(new AnalyzeBacktestResultsAction(
            new FluctuationStressTestProcess,
            new MonteCarloSimulationProcess,
            new BacktestClusterAnalysisProcess,
        ));
        $request = BacktestResultsRequest::create('/workbench/tests/results?page=2', 'GET');
        $request->setValidator(Validator::make($request->all(), $request->rules()));

        $page = $action->execute(
            $request,
            $this->backtestResult(),
            '/workbench/tests/results',
            'Test results',
            'RESEARCH / BACKTEST COMPLETE',
            '/workbench/tests/create',
        );

        $this->assertSame(1, $page['openedOrderCount']);
        $this->assertSame(1, $page['closedOrderCount']);
        $this->assertSame(['commission' => 2.5, 'exchange_fee' => 1.25], $page['tradeCostTotals']);
        $this->assertSame(3.75, $page['totalAdditionalTradeCosts']);
        $this->assertSame('commission', $page['modeledCostLabels']);
        $this->assertSame(2, $page['decisions']->currentPage());
        $this->assertSame(60, $page['decisions']->total());
        $this->assertCount(10, $page['decisions']->items());
        $this->assertSame('/workbench/tests/results', $page['analysisAction']);
        $this->assertSame('Test results', $page['resultsTitle']);
        $this->assertSame('/workbench/tests/create', $page['returnUrl']);
    }

    /**
     * @return array<string, mixed>
     */
    private function backtestResult(): array
    {
        return [
            'initial_capital' => 1000.0,
            'results' => array_map(
                static fn (int $index): array => [
                    'candle' => [
                        'price_date' => sprintf('2026-10-12 10:%02d:00', $index),
                    ],
                    'signal' => 'none',
                ],
                range(0, 59),
            ),
            'order_history' => [
                [
                    'id' => 1,
                    'signal' => 'long',
                    'open_date' => '2026-10-12 09:00:00',
                    'close_date' => '2026-10-12 10:00:00',
                    'profit_loss' => 5.0,
                    'realized_profit_loss' => 5.0,
                    'capital_at_open' => 1000.0,
                    'capital_after_close' => 1005.0,
                    'trade_costs' => [
                        'commission' => 2.5,
                        'exchange_fee' => 1.25,
                    ],
                    'exit_fills' => [],
                ],
                [
                    'id' => 2,
                    'signal' => 'long',
                    'open_date' => '2026-10-12 09:01:00',
                    'close_date' => null,
                    'portfolio_entry_rejected' => true,
                    'trade_costs' => [],
                    'exit_fills' => [],
                ],
            ],
            'execution_config' => [
                'trade_costs' => [
                    'enabled' => true,
                    'components' => [
                        'commission' => ['enabled' => true],
                        'exchange_fee' => ['enabled' => false],
                    ],
                ],
            ],
        ];
    }
}
