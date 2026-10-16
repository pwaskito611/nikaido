<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Actions\MarketTypeBacktests;

use App\Feature\Backtest\Action\MarketTypeBacktests\CombineMarketTypeBacktestResultsAction;
use App\Feature\Backtest\Model\MarketTypeBacktestPairRun;
use App\Feature\Backtest\Model\MarketTypeBacktestRun;
use App\Feature\Backtest\Process\MarketTypeBacktests\PortfolioCapitalSynchronizationProcess;
use InvalidArgumentException;
use Tests\TestCase;
use UnexpectedValueException;

class CombineMarketTypeBacktestResultsActionTest extends TestCase
{
    public function test_it_rejects_empty_pair_results_and_unknown_capital_modes(): void
    {
        $combiner = new CombineMarketTypeBacktestResultsAction(new PortfolioCapitalSynchronizationProcess);
        $run = new MarketTypeBacktestRun([
            'market_type_name' => 'Forex',
            'configuration' => [
                'initial_capital' => 10000,
                'capital_mode' => 'invalid',
            ],
        ]);

        try {
            $combiner->execute($run, []);
            $this->fail('Expected empty pair results to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('At least one successful pair result is required for aggregation.', $exception->getMessage());
        }

        try {
            $combiner->execute($run, [new MarketTypeBacktestPairRun([
                'pair_name' => 'EUR/USD',
                'result' => ['results' => [], 'order_history' => []],
            ])]);
            $this->fail('Expected an unknown capital mode to be rejected.');
        } catch (UnexpectedValueException $exception) {
            $this->assertSame('The market-type run has an unsupported capital mode.', $exception->getMessage());
        }
    }

    public function test_shared_capital_mode_clips_simultaneous_pair_risk_to_one_portfolio_budget(): void
    {
        $run = new MarketTypeBacktestRun([
            'market_type_name' => 'Forex',
            'configuration' => [
                'initial_capital' => 100,
                'capital_mode' => 'shared',
                'timeframe_name' => '1h',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-12 23:59:59',
                'trade_costs' => [
                    'enabled' => true,
                    'commission' => ['enabled' => true, 'method' => 'per_order', 'rate' => 2],
                ],
            ],
        ]);
        $pairRuns = [];
        foreach (['EUR/USD', 'GBP/USD'] as $pair) {
            $pairRuns[] = new MarketTypeBacktestPairRun([
                'pair_name' => $pair,
                'result' => [
                    'initial_capital' => 100,
                    'ending_capital' => 150,
                    'results' => [],
                    'order_history' => [[
                        'id' => 1,
                        'signal' => 'long',
                        'open_date' => '2026-10-10 10:00:00',
                        'close_date' => '2026-10-10 11:00:00',
                        'entry_price' => 100.0,
                        'entry_price_source' => 'ask_open',
                        'stop_loss' => 90.0,
                        'take_profit' => 120.0,
                        'close_price' => 105.0,
                        'strategy_quantity' => 8.0,
                        'quantity_unit' => 'units',
                        'quantity_multiplier' => 1.0,
                        'pnl_multiplier' => 1.0,
                        'requested_position_size' => 8.0,
                        'position_size' => 8.0,
                        'remaining_entry_quantity' => 0.0,
                        'remaining_position_size' => 0.0,
                        'closed_position_size' => 8.0,
                        'risk_amount' => 80.0,
                        'requested_risk_amount' => 80.0,
                        'cancelled_entry_quantity' => 0.0,
                        'gross_realized_profit_loss' => 50.0,
                        'trade_costs' => ['commission' => 4.0],
                        'realized_profit_loss' => 46.0,
                        'profit_loss' => 46.0,
                        'capital_at_open' => 100.0,
                        'capital_after_close' => 146.0,
                        'entry_fills' => [[
                            'quantity' => 8.0,
                            'price' => 100.0,
                            'date' => '2026-10-10 10:00:00',
                            'trade_costs' => ['commission' => 2.0],
                        ]],
                        'exit_fills' => [[
                            'quantity' => 8.0,
                            'price' => 105.0,
                            'date' => '2026-10-10 11:00:00',
                            'profit_loss' => 48.0,
                            'gross_profit_loss' => 50.0,
                            'trade_costs' => ['commission' => 2.0],
                        ]],
                    ]],
                ],
            ]);
        }

        $result = (new CombineMarketTypeBacktestResultsAction(new PortfolioCapitalSynchronizationProcess))
            ->execute($run, $pairRuns);

        $this->assertSame(100.0, $result['initial_capital']);
        $this->assertSame(152.0, $result['ending_capital']);
        $this->assertSame(0.6, $result['order_history'][0]['portfolio_allocation_factor']);
        $this->assertSame(0.6, $result['order_history'][1]['portfolio_allocation_factor']);
        $this->assertSame(4.8, $result['order_history'][0]['position_size']);
        $this->assertSame(26.0, $result['order_history'][0]['profit_loss']);
        $this->assertSame(4.0, $result['order_history'][0]['trade_costs']['commission']);
    }

    public function test_separate_capital_mode_sums_each_pair_account_and_updates_aggregate_equity(): void
    {
        $run = new MarketTypeBacktestRun([
            'market_type_name' => 'Forex',
            'configuration' => [
                'initial_capital' => 10000,
                'capital_mode' => 'separate',
                'timeframe_name' => '1h',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-12 23:59:59',
            ],
        ]);
        $pairRuns = [
            new MarketTypeBacktestPairRun([
                'pair_name' => 'GBP/USD',
                'result' => [
                    'initial_capital' => 8000,
                    'ending_capital' => 7980,
                    'results' => [['candle' => ['price_date' => '2026-10-12 10:02:00']]],
                    'order_history' => [[
                        'id' => 1,
                        'signal' => 'long',
                        'open_date' => '2026-10-12 10:00:00',
                        'close_date' => '2026-10-12 10:03:00',
                        'entry_price' => 100.0,
                        'entry_price_source' => 'ask_open',
                        'stop_loss' => 90.0,
                        'take_profit' => 120.0,
                        'close_price' => 98.0,
                        'strategy_quantity' => 1.0,
                        'quantity_unit' => 'units',
                        'quantity_multiplier' => 1.0,
                        'pnl_multiplier' => 1.0,
                        'requested_position_size' => 1.0,
                        'position_size' => 1.0,
                        'remaining_entry_quantity' => 0.0,
                        'remaining_position_size' => 0.0,
                        'closed_position_size' => 1.0,
                        'risk_amount' => 10.0,
                        'requested_risk_amount' => 10.0,
                        'cancelled_entry_quantity' => 0.0,
                        'cancelled_exit_quantity' => 0.0,
                        'realized_profit_loss' => -20.0,
                        'profit_loss' => -20,
                        'capital_at_open' => 8000.0,
                        'entry_fills' => [],
                        'exit_fills' => [],
                    ]],
                ],
            ]),
            new MarketTypeBacktestPairRun([
                'pair_name' => 'EUR/USD',
                'result' => [
                    'initial_capital' => 12000,
                    'ending_capital' => 12050,
                    'results' => [['candle' => ['price_date' => '2026-10-12 10:01:00']]],
                    'order_history' => [[
                        'id' => 1,
                        'signal' => 'long',
                        'open_date' => '2026-10-12 10:00:00',
                        'close_date' => '2026-10-12 10:02:00',
                        'entry_price' => 100.0,
                        'entry_price_source' => 'ask_open',
                        'stop_loss' => 90.0,
                        'take_profit' => 120.0,
                        'close_price' => 105.0,
                        'strategy_quantity' => 1.0,
                        'quantity_unit' => 'units',
                        'quantity_multiplier' => 1.0,
                        'pnl_multiplier' => 1.0,
                        'requested_position_size' => 1.0,
                        'position_size' => 1.0,
                        'remaining_entry_quantity' => 0.0,
                        'remaining_position_size' => 0.0,
                        'closed_position_size' => 1.0,
                        'risk_amount' => 10.0,
                        'requested_risk_amount' => 10.0,
                        'cancelled_entry_quantity' => 0.0,
                        'cancelled_exit_quantity' => 0.0,
                        'realized_profit_loss' => 50.0,
                        'profit_loss' => 50,
                        'capital_at_open' => 12000.0,
                        'entry_fills' => [],
                        'exit_fills' => [],
                    ]],
                ],
            ]),
        ];

        $result = (new CombineMarketTypeBacktestResultsAction(new PortfolioCapitalSynchronizationProcess))
            ->execute($run, $pairRuns);

        $this->assertSame('Overall · Forex', $result['pair']);
        $this->assertSame(['EUR/USD', 'GBP/USD'], array_column($result['results'], 'pair'));
        $this->assertSame(['EUR/USD', 'GBP/USD'], array_column($result['order_history'], 'pair'));
        $this->assertSame(20030.0, $result['ending_capital']);
        $this->assertSame(20050.0, $result['order_history'][0]['capital_after_close']);
        $this->assertSame(20030.0, $result['order_history'][1]['capital_after_close']);
        $this->assertSame(20000.0, $result['initial_capital']);
        $this->assertSame(10000.0, $result['average_initial_capital_per_pair']);
        $this->assertSame(10015.0, $result['average_ending_capital_per_pair']);
    }
}
