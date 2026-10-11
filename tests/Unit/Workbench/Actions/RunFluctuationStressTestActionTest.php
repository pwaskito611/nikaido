<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Actions;

use App\Workbench\Actions\RunFluctuationStressTestAction;
use InvalidArgumentException;
use Tests\TestCase;

class RunFluctuationStressTestActionTest extends TestCase
{
    public function test_adverse_mode_moves_unreached_long_excursion_to_stop_loss(): void
    {
        $backtest = $this->backtest($this->longOrder());

        $result = (new RunFluctuationStressTestAction)->execute($backtest, 'adverse', 1, 1);

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(90.0, $result['orders'][0]['close_price']);
        $this->assertSame(-10.0, $result['orders'][0]['profit_loss']);
        $this->assertSame(-10.0, $result['scenario']['net_profit']);
        $this->assertSame(5.0, $result['baseline']['net_profit']);
        $this->assertSame('close', $backtest['order_history'][0]['close_reason']);
    }

    public function test_favorable_mode_moves_unreached_long_excursion_to_take_profit(): void
    {
        $result = (new RunFluctuationStressTestAction)->execute(
            $this->backtest($this->longOrder()),
            'favorable',
            1,
            1,
        );

        $this->assertSame('take_profit', $result['orders'][0]['close_reason']);
        $this->assertSame(120.0, $result['orders'][0]['close_price']);
        $this->assertSame(20.0, $result['scenario']['net_profit']);
        $this->assertEquals(100.0, $result['scenario']['take_profit_frequency']);
    }

    public function test_combined_mode_applies_both_stresses_and_prefers_stop_when_both_levels_hit(): void
    {
        $result = (new RunFluctuationStressTestAction)->execute(
            $this->backtest($this->longOrder()),
            'combined',
            1,
            1,
        );

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(90.0, $result['orders'][0]['close_price']);
        $this->assertEquals(100.0, $result['scenario']['stop_loss_frequency']);
        $this->assertSame(0, $result['scenario']['take_profit_count']);
    }

    public function test_combined_mode_applies_short_excursion_formulas(): void
    {
        $order = $this->longOrder();
        $order['signal'] = 'short';
        $order['entry_price'] = 100.0;
        $order['stop_loss'] = 110.0;
        $order['take_profit'] = 80.0;
        $order['adverse_extreme']['price'] = 105.0;
        $order['favorable_extreme']['price'] = 90.0;

        $result = (new RunFluctuationStressTestAction)->execute(
            $this->backtest($order),
            'combined',
            1,
            1,
        );

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(110.0, $result['orders'][0]['stress_adverse_extreme']['price']);
        $this->assertSame(80.0, $result['orders'][0]['stress_favorable_extreme']['price']);
    }

    public function test_existing_stop_loss_outcome_is_not_retroactively_changed(): void
    {
        $order = $this->longOrder();
        $order['close_reason'] = 'stop_loss';
        $order['close_price'] = 89.5;
        $order['profit_loss'] = -10.5;
        $order['adverse_extreme']['price'] = 85.0;

        $result = (new RunFluctuationStressTestAction)->execute(
            $this->backtest($order),
            'combined',
            0,
            1,
        );

        $this->assertSame('stop_loss', $result['orders'][0]['close_reason']);
        $this->assertSame(89.5, $result['orders'][0]['close_price']);
    }

    public function test_existing_take_profit_outcome_is_not_retroactively_changed(): void
    {
        $order = $this->longOrder();
        $order['close_reason'] = 'take_profit';
        $order['close_price'] = 120.5;
        $order['profit_loss'] = 20.5;
        $order['favorable_extreme']['price'] = 125.0;

        $result = (new RunFluctuationStressTestAction)->execute(
            $this->backtest($order),
            'favorable',
            0,
            0.75,
        );

        $this->assertSame('take_profit', $result['orders'][0]['close_reason']);
        $this->assertSame(120.5, $result['orders'][0]['close_price']);
    }

    public function test_it_rejects_unknown_mode(): void
    {
        $action = new RunFluctuationStressTestAction;

        $this->expectException(InvalidArgumentException::class);
        $action->execute($this->backtest($this->longOrder()), 'unknown', 0, 0);
    }

    public function test_it_rejects_stress_outside_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RunFluctuationStressTestAction)->execute(
            $this->backtest($this->longOrder()),
            'adverse',
            1.01,
            0,
        );
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{initial_capital: float, order_history: list<array<string, mixed>>}
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
