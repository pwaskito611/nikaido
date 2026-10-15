<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Domain;

use App\Feature\Backtest\Data\StressTestInput;
use App\Feature\Backtest\Process\FluctuationStressTestProcess;
use InvalidArgumentException;
use Tests\TestCase;
use UnexpectedValueException;

class FluctuationStressTestDomainTest extends TestCase
{
    public function test_x_adds_to_the_winning_trades_baseline_drawdown(): void
    {
        $backtest = $this->backtest($this->longOrder());

        $result = $this->runStressTest($backtest, 1);

        $this->assertSame('stress_test', $result['orders'][0]['close_reason']);
        $this->assertSame(100.0, $result['orders'][0]['close_price']);
        $this->assertSame(0.0, $result['orders'][0]['profit_loss']);
        $this->assertSame(0.0, $result['scenario']['net_profit']);
        $this->assertSame(5.0, $result['baseline']['net_profit']);
        $this->assertSame(1, $result['scenario']['stress_test_count']);
        $this->assertSame(0, $result['scenario']['take_profit_count']);
        $this->assertSame(0, $result['scenario']['other_exit_count']);
        $this->assertEquals(100.0, $result['scenario']['stress_test_frequency']);
        $this->assertSame(0, $result['scenario']['profitable_trade_count']);
        $this->assertSame(0, $result['scenario']['losing_trade_count']);
        $this->assertSame(1, $result['scenario']['breakeven_trade_count']);
        $this->assertSame(1, $result['scenario']['breakeven_stress_exit_count']);
        $this->assertEquals(100.0, $result['scenario']['breakeven_stress_exit_frequency']);
        $this->assertSame(1.0, $result['x']);
    }

    public function test_profitable_trade_uses_the_adverse_excursion_before_profit(): void
    {
        $result = $this->runStressTest($this->backtest($this->longOrder()), 0);

        $this->assertSame('close', $result['orders'][0]['close_reason']);
        $this->assertSame(105.0, $result['orders'][0]['close_price']);
        $this->assertSame(5.0, $result['scenario']['net_profit']);
        $this->assertSame($result['baseline']['net_profit'], $result['scenario']['net_profit']);
    }

    public function test_losing_trade_uses_the_favorable_excursion_before_loss(): void
    {
        $order = $this->longOrder();
        $order['profit_loss'] = -5.0;
        $order['close_price'] = 95.0;
        $order['favorable_extreme']['price'] = 105.0;
        $result = $this->runStressTest($this->backtest($order), 0.5);

        $this->assertSame('stress_test', $result['orders'][0]['close_reason']);
        $this->assertSame(92.5, $result['orders'][0]['close_price']);
        $this->assertSame(-7.5, $result['orders'][0]['profit_loss']);
        $this->assertSame(1, $result['scenario']['losing_trade_count']);
        $this->assertSame(1, $result['scenario']['losing_stress_exit_count']);
        $this->assertSame(0, $result['scenario']['profitable_stress_exit_count']);
    }

    public function test_stress_exit_summary_separates_profitable_and_losing_stress_exits(): void
    {
        $profitableStressExit = $this->longOrder();
        $profitableStressExit['adverse_extreme']['price'] = 99.0;
        $losingStressExit = $this->longOrder();
        $losingStressExit['profit_loss'] = -5.0;
        $losingStressExit['close_price'] = 95.0;
        $losingStressExit['favorable_extreme']['price'] = 101.0;
        $backtest = $this->backtest($profitableStressExit);
        $backtest['order_history'][] = $losingStressExit;

        $result = $this->runStressTest($backtest, 0.5);

        $this->assertSame(2, $result['scenario']['stress_test_count']);
        $this->assertSame(1, $result['scenario']['profitable_stress_exit_count']);
        $this->assertSame(1, $result['scenario']['losing_stress_exit_count']);
        $this->assertEquals(50.0, $result['scenario']['profitable_stress_exit_frequency']);
        $this->assertEquals(50.0, $result['scenario']['losing_stress_exit_frequency']);
        $this->assertSame(1, $result['scenario']['profitable_trade_count']);
        $this->assertSame(1, $result['scenario']['losing_trade_count']);
        $this->assertSame(2, $result['scenario']['trade_count']);
    }

    public function test_stress_uses_baseline_closed_quantity_without_reapplying_partial_fill_ratio(): void
    {
        $order = $this->longOrder();
        $order['entry_price'] = 100.0;
        $order['close_price'] = 105.0;
        $order['requested_position_size'] = 2.0;
        $order['position_size'] = 2.0;
        $order['pnl_multiplier'] = 10.0;
        $order['closed_position_size'] = 2.0;
        $backtest = $this->backtest($order);
        $backtest['execution_config'] = [
            'slippage_enabled' => true,
            'slippage_pips' => 1.0,
            'slippage_pip_size' => 0.5,
            'partial_fill_enabled' => true,
            'partial_fill_ratio' => 0.5,
        ];

        $result = $this->runStressTest($backtest, 3.01);

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(89.5, $result['orders'][0]['close_price']);
        $this->assertEqualsWithDelta(15.05, $result['orders'][0]['stress_distance'], 0.000001);
        $this->assertSame(-305.0, $result['orders'][0]['profit_loss']);
    }

    public function test_stress_slippage_uses_the_order_quote_currency_pip_size(): void
    {
        $order = $this->longOrder();
        $order['pair'] = 'EUR/JPY';
        $backtest = $this->backtest($order);
        $backtest['execution_config'] = [
            'slippage_enabled' => true,
            'slippage_pips' => 1.0,
        ];

        $result = $this->runStressTest($backtest, 15.0);

        $this->assertSame(89.99, $result['orders'][0]['close_price']);
        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
    }

    public function test_short_winner_uses_the_adverse_excursion_before_profit(): void
    {
        $order = $this->longOrder();
        $order['signal'] = 'short';
        $order['entry_price'] = 100.0;
        $order['stop_loss'] = 110.0;
        $order['take_profit'] = 80.0;
        $order['adverse_extreme']['price'] = 105.0;
        $order['favorable_extreme']['price'] = 90.0;

        $result = $this->runStressTest($this->backtest($order), 1);

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(110.0, $result['orders'][0]['close_price']);
    }

    public function test_existing_stop_loss_outcome_is_repriced_from_the_runup_before_loss(): void
    {
        $order = $this->longOrder();
        $order['close_reason'] = 'stop_loss';
        $order['close_price'] = 89.5;
        $order['profit_loss'] = -10.5;
        $order['adverse_extreme']['price'] = 85.0;

        $result = $this->runStressTest($this->backtest($order), 0);

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(89.5, $result['orders'][0]['close_price']);
    }

    public function test_existing_take_profit_outcome_is_repriced_from_the_drawdown_before_profit(): void
    {
        $order = $this->longOrder();
        $order['close_reason'] = 'take_profit';
        $order['close_price'] = 120.5;
        $order['profit_loss'] = 20.5;
        $order['favorable_extreme']['price'] = 125.0;

        $result = $this->runStressTest($this->backtest($order), 0);

        $this->assertSame('take_profit', $result['orders'][0]['close_reason']);
        $this->assertSame(120.5, $result['orders'][0]['close_price']);
        $this->assertSame(20.5, $result['orders'][0]['profit_loss']);
    }

    public function test_it_rejects_negative_x(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->runStressTest($this->backtest($this->longOrder()), -0.01);
    }

    public function test_it_rejects_non_positive_position_size(): void
    {
        $order = $this->longOrder();
        $order['position_size'] = 0.0;

        $this->expectException(UnexpectedValueException::class);
        StressTestInput::fromPersistedBacktest($this->backtest($order));
    }

    public function test_it_rejects_malformed_present_extreme_instead_of_treating_it_as_missing(): void
    {
        $order = $this->longOrder();
        $order['adverse_extreme']['price'] = 'invalid';

        $this->expectException(UnexpectedValueException::class);
        StressTestInput::fromPersistedBacktest($this->backtest($order));
    }

    public function test_it_rejects_invalid_extreme_timestamp(): void
    {
        $order = $this->longOrder();
        $order['adverse_extreme']['date'] = '2026-02-30 09:30:00';

        $this->expectException(UnexpectedValueException::class);
        StressTestInput::fromPersistedBacktest($this->backtest($order));
    }

    public function test_it_rejects_unknown_extreme_price_source(): void
    {
        $order = $this->longOrder();
        $order['adverse_extreme']['source'] = 'untrusted_price_source';

        $this->expectException(UnexpectedValueException::class);
        StressTestInput::fromPersistedBacktest($this->backtest($order));
    }

    public function test_it_preserves_extreme_date_and_source_in_stress_output(): void
    {
        $order = $this->longOrder();

        $result = $this->runStressTest($this->backtest($order), 1.0);

        $this->assertSame([
            'price' => 95.0,
            'date' => '2026-10-11 09:30:00',
            'source' => 'bid_low',
        ], $result['orders'][0]['stress_extreme']);
    }

    public function test_it_rejects_a_malformed_order_pair(): void
    {
        $backtest = $this->backtest($this->longOrder());
        $backtest['pair'] = 'EUR/JPY/USD';

        $this->expectException(UnexpectedValueException::class);
        StressTestInput::fromPersistedBacktest($backtest);
    }

    public function test_it_preserves_nested_order_payload_fields_at_the_serialization_boundary(): void
    {
        $order = $this->longOrder();
        $order['entry_fills'] = [[
            'quantity' => 1.0,
            'price' => 100.0,
            'date' => '2026-10-11 09:00:00',
            'source' => 'ask_open',
        ]];
        $order['integration_metadata'] = [
            'enabled' => true,
            'labels' => ['baseline', 'stress'],
        ];

        $result = $this->runStressTest($this->backtest($order), 0.0);

        $this->assertSame($order['entry_fills'], $result['orders'][0]['entry_fills']);
        $this->assertSame($order['integration_metadata'], $result['orders'][0]['integration_metadata']);
    }

    /** @param array<string, mixed> $backtest
     * @return array<string, mixed>
     */
    private function runStressTest(array $backtest, float $x): array
    {
        return (new FluctuationStressTestProcess)
            ->execute(StressTestInput::fromPersistedBacktest($backtest), $x)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function backtest(array $order): array
    {
        return ['initial_capital' => 1000.0, 'order_history' => [$order]];
    }

    /**
     * @return array<string, mixed>
     */
    private function longOrder(): array
    {
        return [
            'signal' => 'long',
            'entry_price' => 100.0,
            'stop_loss' => 90.0,
            'take_profit' => 120.0,
            'position_size' => 1.0,
            'close_reason' => 'close',
            'close_date' => '2026-10-11 10:00:00',
            'close_price' => 105.0,
            'profit_loss' => 5.0,
            'capital_after_close' => 1005.0,
            'adverse_extreme' => [
                'price' => 95.0,
                'date' => '2026-10-11 09:30:00',
                'source' => 'bid_low',
            ],
            'favorable_extreme' => [
                'price' => 110.0,
                'date' => '2026-10-11 09:45:00',
                'source' => 'bid_high',
            ],
        ];
    }
}
