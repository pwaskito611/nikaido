<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\MarketTypeBacktests;

use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioExitFill;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioManagedOrder;
use App\Feature\Backtest\Process\MarketTypeBacktests\PortfolioCapitalSynchronizationProcess as SynchronizeMarketTypePortfolioCapitalAction;
use Exact\Collection\Vec;
use InvalidArgumentException;
use Tests\TestCase;

class PortfolioCapitalSynchronizationDomainTest extends TestCase
{
    public function test_it_leaves_strategy_quantity_unchanged_when_risk_capital_is_sufficient(): void
    {
        $order = $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 8, 80, 50);
        $order['capital_at_open'] = 1000.0;

        $result = $this->synchronize(
            1000,
            [$order],
        );

        $this->assertSame(1.0, $result['orders'][0]['portfolio_allocation_factor']);
        $this->assertSame(8.0, $result['orders'][0]['position_size']);
        $this->assertSame(80.0, $result['orders'][0]['risk_amount']);
        $this->assertSame(1050.0, $result['ending_capital']);
    }

    public function test_fractional_costs_keep_shared_portfolio_equity_at_break_even(): void
    {
        $order = $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 1, 10, 0.3);
        $order['trade_costs'] = ['commission' => 0.1, 'exchange_fee' => 0.2];
        $order['gross_realized_profit_loss'] = 0.3;
        $order['exit_fills'][0]['gross_profit_loss'] = 0.3;
        $order['exit_fills'][0]['trade_costs'] = ['commission' => 0.1, 'exchange_fee' => 0.2];

        $result = $this->synchronize(100, [$order]);

        $this->assertSame(100.0, $result['ending_capital']);
        $this->assertSame(0.0, $result['orders'][0]['profit_loss']);
        $this->assertSame(0.0, $result['orders'][0]['exit_fills'][0]['profit_loss']);
    }

    public function test_decimal_risk_demands_equal_to_available_capital_are_not_scaled_down(): void
    {
        $orders = [
            $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 0.1, 0.1, 0.0),
            $this->order('GBP/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 0.2, 0.2, 0.0),
        ];
        $orders[0]['capital_at_open'] = 0.3;
        $orders[1]['capital_at_open'] = 0.3;

        $result = $this->synchronize(0.3, $orders);

        $this->assertSame(1.0, $result['orders'][0]['portfolio_allocation_factor']);
        $this->assertSame(1.0, $result['orders'][1]['portfolio_allocation_factor']);
    }

    public function test_later_pair_entry_is_resized_to_global_capital_after_an_earlier_pair_loss(): void
    {
        $laterPair = $this->order('GBP/USD', 1, '2020-01-01 00:00:00', '2020-01-02 00:00:00', 1, 1, 10);
        $laterPair['capital_at_open'] = 100.0;
        $earlierPair = $this->order('EUR/USD', 1, '2017-01-01 00:00:00', '2019-01-01 00:00:00', 1, 50, -50);
        $earlierPair['capital_at_open'] = 100.0;

        $result = $this->synchronize(100.0, [$laterPair, $earlierPair]);

        $this->assertSame(1.0, $result['orders'][1]['portfolio_allocation_factor']);
        $this->assertSame(0.5, $result['orders'][0]['portfolio_allocation_factor']);
        $this->assertSame(0.5, $result['orders'][0]['position_size']);
        $this->assertSame(5.0, $result['orders'][0]['profit_loss']);
        $this->assertSame(55.0, $result['ending_capital']);
    }

    public function test_same_timestamp_exit_order_is_stable_when_pair_results_arrive_in_a_different_order(): void
    {
        $eur = $this->order('EUR/USD', 1, '2020-01-01 00:00:00', '2020-01-02 00:00:00', 1, 1, 0.1);
        $gbp = $this->order('GBP/USD', 1, '2020-01-01 00:00:00', '2020-01-02 00:00:00', 1, 1, 0.2);
        $eur['capital_at_open'] = 10.0;
        $gbp['capital_at_open'] = 10.0;

        $first = $this->synchronize(10.0, [$gbp, $eur]);
        $second = $this->synchronize(10.0, [$eur, $gbp]);
        $firstCapitalAfter = array_column($first['orders'], 'capital_after_close', 'pair');
        $secondCapitalAfter = array_column($second['orders'], 'capital_after_close', 'pair');
        ksort($firstCapitalAfter);
        ksort($secondCapitalAfter);

        $this->assertSame(['EUR/USD' => 10.1, 'GBP/USD' => 10.3], $firstCapitalAfter);
        $this->assertSame($firstCapitalAfter, $secondCapitalAfter);
    }

    public function test_it_releases_exited_risk_before_proportionally_allocating_simultaneous_entries(): void
    {
        $orders = [
            $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 8, 80, 50),
            $this->order('GBP/USD', 1, '2026-10-10 11:00:00', '2026-10-10 12:00:00', 10, 100, 40),
            $this->order('USD/CHF', 1, '2026-10-10 11:00:00', '2026-10-10 12:00:00', 10, 100, 40),
        ];

        $result = $this->synchronize(100, $orders);

        $this->assertSame(210.0, $result['ending_capital']);
        $this->assertSame(0.75, $result['orders'][1]['portfolio_allocation_factor']);
        $this->assertSame(0.75, $result['orders'][2]['portfolio_allocation_factor']);
        $this->assertSame(7.5, $result['orders'][1]['position_size']);
        $this->assertSame(30.0, $result['orders'][1]['profit_loss']);
        $this->assertSame(150.0, $result['orders'][1]['capital_at_open']);
        $this->assertSame(210.0, $result['orders'][2]['capital_after_close']);
    }

    public function test_it_rejects_an_entry_when_no_risk_capital_is_available(): void
    {
        $orders = [
            $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 10, 100, -100),
            $this->order('GBP/USD', 1, '2026-10-10 11:00:00', '2026-10-10 12:00:00', 10, 100, 50),
        ];

        $result = $this->synchronize(100, $orders);

        $this->assertTrue($result['orders'][1]['portfolio_entry_rejected']);
        $this->assertSame('portfolio_capital_unavailable', $result['orders'][1]['close_reason']);
        $this->assertNull($result['orders'][1]['close_date']);
        $this->assertNull($result['orders'][1]['profit_loss']);
        $this->assertSame(0.0, $result['orders'][1]['position_size']);
    }

    public function test_cancelled_unfilled_entry_quantity_does_not_reserve_risk_capital(): void
    {
        $partiallyFilled = $this->order(
            'EUR/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 11:00:00',
            10,
            100,
            20,
        );
        $partiallyFilled['cancelled_entry_quantity'] = 7.5;
        $partiallyFilled['position_size'] = 2.5;
        $partiallyFilled['closed_position_size'] = 2.5;
        $partiallyFilled['risk_amount'] = 25.0;
        $partiallyFilled['entry_fills'][0]['quantity'] = 2.5;
        $partiallyFilled['exit_fills'][0]['quantity'] = 2.5;
        $partiallyFilled['capital_at_open'] = 50.0;
        $other = $this->order(
            'GBP/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 11:00:00',
            5,
            50,
            20,
        );
        $other['capital_at_open'] = 50.0;

        $result = $this->synchronize(
            50,
            [$partiallyFilled, $other],
            UnfilledOrderPolicy::Cancel,
        );

        $this->assertEquals(2 / 3, $result['orders'][0]['portfolio_allocation_factor']);
        $this->assertEquals(2 / 3, $result['orders'][1]['portfolio_allocation_factor']);
    }

    public function test_partial_exit_releases_only_closed_quantity_risk_before_next_entry(): void
    {
        $partiallyClosed = $this->order(
            'EUR/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 12:00:00',
            10,
            100,
            40,
        );
        $partiallyClosed['remaining_position_size'] = 10.0;
        $partiallyClosed['closed_position_size'] = 0.0;
        $partiallyClosed['close_date'] = null;
        $partiallyClosed['profit_loss'] = null;
        $partiallyClosed['exit_fills'] = [
            ['quantity' => 5.0, 'price' => 104.0, 'date' => '2026-10-10 11:00:00', 'profit_loss' => 20.0],
            ['quantity' => 5.0, 'price' => 104.0, 'date' => '2026-10-10 12:00:00', 'profit_loss' => 20.0],
        ];
        $newEntry = $this->order(
            'GBP/USD',
            1,
            '2026-10-10 11:00:00',
            '2026-10-10 12:00:00',
            10,
            100,
            50,
        );

        $result = $this->synchronize(
            50,
            [$partiallyClosed, $newEntry],
        );

        $this->assertSame(0.5, $result['orders'][0]['portfolio_allocation_factor']);
        $this->assertEqualsWithDelta(0.35, $result['orders'][1]['portfolio_allocation_factor'], 0.000001);
        $this->assertSame(2.5, $result['orders'][0]['exit_fills'][0]['quantity']);
        $this->assertSame(10.0, $result['orders'][0]['exit_fills'][0]['profit_loss']);
        $this->assertEqualsWithDelta(3.5, $result['orders'][1]['position_size'], 0.000001);
        $this->assertSame(87.5, $result['ending_capital']);
    }

    public function test_separate_capital_timeline_tracks_profit_from_each_exit_fill(): void
    {
        $persistedOrder = $this->order(
            'EUR/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 12:00:00',
            2,
            20,
            20,
        );
        $persistedOrder['capital_at_open'] = 75.0;
        $persistedOrder['exit_fills'] = [
            ['quantity' => 1.0, 'price' => 106.0, 'date' => '2026-10-10 11:00:00', 'profit_loss' => 12.0],
            ['quantity' => 1.0, 'price' => 104.0, 'date' => '2026-10-10 12:00:00', 'profit_loss' => 8.0],
        ];
        $order = PortfolioManagedOrder::fromPersistedArray($persistedOrder, 0);

        $result = (new SynchronizeMarketTypePortfolioCapitalAction)
            ->applySeparateCapitalTimeline(100.0, Vec::of($order));
        $serializedOrder = $result->toArray()['orders'][0];

        $this->assertSame(120.0, $result->endingCapital);
        $this->assertSame(112.0, $serializedOrder['exit_fills'][0]['capital_after']);
        $this->assertSame(120.0, $serializedOrder['exit_fills'][1]['capital_after']);
        $this->assertSame(120.0, $serializedOrder['capital_after_close']);
        $this->assertSame(75.0, $serializedOrder['capital_at_open']);
        $this->assertSame('long', $serializedOrder['signal']);
        $this->assertSame('ask_open', $serializedOrder['entry_price_source']);
        $this->assertSame('units', $serializedOrder['quantity_unit']);
    }

    public function test_it_requires_finite_positive_capital_and_complete_order_risk_data(): void
    {
        $manager = new SynchronizeMarketTypePortfolioCapitalAction;

        try {
            $manager->execute(0, Vec::empty());
            $this->fail('Expected invalid portfolio capital to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Shared portfolio capital must be a positive finite number.', $exception->getMessage());
        }

        $order = $this->order('EUR/USD', 1, '2026-10-10 10:00:00', '2026-10-10 11:00:00', 8, 80, 50);
        unset($order['requested_risk_amount']);

        try {
            $this->synchronize(100, [$order]);
            $this->fail('Expected incomplete order risk data to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A portfolio-managed order must have a positive numeric requested_risk_amount.',
                $exception->getMessage(),
            );
        }
    }

    public function test_persisted_optional_values_are_rejected_when_their_domain_type_is_invalid(): void
    {
        $invalidProfitLoss = $this->order(
            'EUR/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 11:00:00',
            8,
            80,
            50,
        );
        $invalidProfitLoss['profit_loss'] = 'not-a-number';

        try {
            PortfolioManagedOrder::fromPersistedArray($invalidProfitLoss, 0);
            $this->fail('Expected invalid optional P/L to be rejected.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertSame(
                'Portfolio order profit_loss must be a finite number when present.',
                $exception->getMessage(),
            );
        }

        $invalidRejectedState = $this->order(
            'EUR/USD',
            1,
            '2026-10-10 10:00:00',
            '2026-10-10 11:00:00',
            8,
            80,
            50,
        );
        $invalidRejectedState['portfolio_entry_rejected'] = 'false';

        try {
            PortfolioManagedOrder::fromPersistedArray($invalidRejectedState, 0);
            $this->fail('Expected non-boolean entry rejection state to be rejected.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertSame('Portfolio entry-rejected state must be boolean.', $exception->getMessage());
        }

        try {
            PortfolioExitFill::fromPersistedArray([
                'quantity' => 1.0,
                'date' => 'invalid-date',
                'profit_loss' => 10.0,
            ]);
            $this->fail('Expected invalid exit-fill date to be rejected.');
        } catch (\UnexpectedValueException $exception) {
            $this->assertSame(
                'Portfolio exit-fill dates must be valid date strings when present.',
                $exception->getMessage(),
            );
        }
    }

    public function test_exit_fill_source_and_reason_are_not_validated_as_dates(): void
    {
        $fill = PortfolioExitFill::fromPersistedArray([
            'quantity' => 1.0,
            'price' => 1.1,
            'date' => '2026-10-10 11:00:00',
            'source' => 'bid_open',
            'reason' => 'stop_loss',
            'profit_loss' => 10.0,
        ]);

        $this->assertSame('bid_open', $fill->source->getOrNull());
        $this->assertSame('stop_loss', $fill->reason->getOrNull());
        $this->assertSame('2026-10-10 11:00:00', $fill->date->getOrNull());
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array{orders: list<array<string, mixed>>, ending_capital: float}
     */
    private function synchronize(
        float $initialCapital,
        array $orders,
        UnfilledOrderPolicy $policy = UnfilledOrderPolicy::Keep,
    ): array {
        $typedOrders = Vec::empty();
        foreach ($orders as $index => $order) {
            $typedOrders = $typedOrders->append(PortfolioManagedOrder::fromPersistedArray($order, $index));
        }

        return (new SynchronizeMarketTypePortfolioCapitalAction)
            ->execute($initialCapital, $typedOrders, $policy)
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function order(
        string $pair,
        int $id,
        string $openDate,
        string $closeDate,
        float $quantity,
        float $risk,
        float $profitLoss,
    ): array {
        return [
            'id' => $id,
            'pair' => $pair,
            'signal' => 'long',
            'open_date' => $openDate,
            'close_date' => $closeDate,
            'entry_price' => 100.0,
            'entry_price_source' => 'ask_open',
            'stop_loss' => 90.0,
            'take_profit' => 120.0,
            'close_price' => 105.0,
            'strategy_quantity' => $quantity,
            'quantity_unit' => 'units',
            'quantity_multiplier' => 1.0,
            'pnl_multiplier' => 1.0,
            'requested_position_size' => $quantity,
            'position_size' => $quantity,
            'remaining_entry_quantity' => 0.0,
            'remaining_position_size' => 0.0,
            'closed_position_size' => $quantity,
            'risk_amount' => $risk,
            'requested_risk_amount' => $risk,
            'cancelled_entry_quantity' => 0.0,
            'cancelled_exit_quantity' => 0.0,
            'realized_profit_loss' => $profitLoss,
            'profit_loss' => $profitLoss,
            'capital_at_open' => 100.0,
            'capital_after_close' => null,
            'entry_fills' => [[
                'quantity' => $quantity,
                'price' => 100.0,
                'date' => $openDate,
            ]],
            'exit_fills' => [[
                'quantity' => $quantity,
                'price' => 105.0,
                'date' => $closeDate,
                'profit_loss' => $profitLoss,
            ]],
        ];
    }
}
