<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificPairTestRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_receives_the_test_configuration_from_a_post_request(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ]);

        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 08:59:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quotes('1.100000', '1.200000'),
        ]);
        $activeCandle = MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.200000',
            'close' => '1.300000',
            ...$this->quotes('1.200000', '1.300000'),
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'eur',
            'quote' => 'usd',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => '09:00',
            'active_until' => '17:00',
            'excluded_days' => [6, 7],
            'slippage_enabled' => true,
            'slippage_pips' => 1.5,
            'slippage_pip_size' => 0.0002,
            'partial_fill_enabled' => true,
            'partial_fill_ratio' => 0.4,
            'unfilled_order_policy' => 'cancel',
            'trade_costs' => [
                'enabled' => true,
                'commission' => [
                    'enabled' => true,
                    'method' => 'transaction_value_percent',
                    'rate' => 0.25,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.candle.id', $activeCandle->id)
            ->assertJsonPath('data.0.result', false)
            ->assertJsonPath('data.0.signal', 'none')
            ->assertJsonPath('data.0.quantity', null)
            ->assertJsonPath('data.0.quantity_unit', 'units')
            ->assertJsonPath('quantity_multiplier', 1)
            ->assertJsonPath('units_per_lot', 1)
            ->assertJsonPath('execution_config.slippage_enabled', true)
            ->assertJsonPath('execution_config.slippage_mode', 'fixed')
            ->assertJsonPath('execution_config.slippage_direction', 'adverse_only')
            ->assertJsonPath('execution_config.fixed_slippage', 1.5)
            ->assertJsonPath('execution_config.slippage_pips', 1.5)
            ->assertJsonPath('execution_config.slippage_pip_size', 0.0002)
            ->assertJsonPath('execution_config.partial_fill_enabled', true)
            ->assertJsonPath('execution_config.partial_fill_ratio', 0.4)
            ->assertJsonPath('execution_config.unfilled_order_policy', 'cancel')
            ->assertJsonPath('execution_config.trade_costs.enabled', true)
            ->assertJsonPath('execution_config.trade_costs.components.commission.enabled', true)
            ->assertJsonPath('execution_config.trade_costs.components.commission.rate', 0.25)
            ->assertJsonPath('order_history', []);
    }

    public function test_it_returns_a_validation_error_when_the_pair_has_no_cfd_profile(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'CAD',
            'quote' => 'CHF',
            'quantity_unit' => 'units',
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'CAD',
            'quote' => 'CHF',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('market_pair_id');
    }

    public function test_it_rejects_invalid_time_configuration(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            ...$this->cfdProfile(),
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => '17:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active_from');
    }

    public function test_it_validates_enabled_execution_simulation_settings(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'slippage_enabled' => true,
            'slippage_pips' => -1,
            'slippage_direction' => 'favorable_only',
            'partial_fill_enabled' => true,
            'partial_fill_ratio' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slippage_pips', 'slippage_direction', 'partial_fill_ratio']);
    }

    public function test_it_accepts_and_serializes_dynamic_slippage_configuration(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quotes('1.100000', '1.200000'),
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'slippage_enabled' => true,
            'slippage_mode' => 'dynamic',
            'slippage_direction' => 'random',
            'slippage_unit' => 'pips',
            'base_slippage' => 0.5,
            'volatility_factor' => 0.25,
            'volume_impact_factor' => 2,
            'spread_factor' => 0.75,
            'maximum_slippage' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('execution_config.slippage_mode', 'dynamic')
            ->assertJsonPath('execution_config.slippage_direction', 'random')
            ->assertJsonPath('execution_config.slippage_unit', 'pips')
            ->assertJsonPath('execution_config.base_slippage', 0.5)
            ->assertJsonPath('execution_config.volatility_factor', 0.25)
            ->assertJsonPath('execution_config.volume_impact_factor', 2)
            ->assertJsonPath('execution_config.spread_factor', 0.75)
            ->assertJsonPath('execution_config.maximum_slippage', 8);
    }

    public function test_it_rejects_invalid_random_slippage_boundaries_and_normal_parameters(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'slippage_enabled' => true,
            'slippage_mode' => 'random',
            'minimum_slippage' => 3,
            'maximum_slippage' => 1,
            'distribution' => 'normal',
            'mean' => 4,
            'standard_deviation' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['minimum_slippage', 'mean', 'standard_deviation']);
    }

    public function test_it_accepts_and_serializes_volume_based_fill_configuration(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quotes('1.100000', '1.200000'),
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'partial_fill_enabled' => true,
            'partial_fill_mode' => 'volume',
            'max_participation_rate' => 0.25,
            'volume_source' => 'traded_volume',
            'execution_volume_period' => 'candle',
        ])
            ->assertOk()
            ->assertJsonPath('execution_config.partial_fill_mode', 'volume')
            ->assertJsonPath('execution_config.max_participation_rate', 0.25)
            ->assertJsonPath('execution_config.volume_source', 'traded_volume')
            ->assertJsonPath('execution_config.execution_volume_period', 'candle');
    }

    public function test_it_rejects_invalid_volume_fill_configuration(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'partial_fill_enabled' => true,
            'partial_fill_mode' => 'volume',
            'max_participation_rate' => 1.01,
            'volume_source' => 'tick_volume',
            'execution_volume_period' => 'week',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'max_participation_rate',
                'volume_source',
                'execution_volume_period',
            ]);
    }

    public function test_it_rejects_unsupported_trade_cost_methods_and_negative_rates(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
            'trade_costs' => [
                'enabled' => true,
                'commission' => [
                    'enabled' => true,
                    'method' => 'round_trip_guess',
                    'rate' => -0.25,
                ],
                'transaction_tax' => [
                    'enabled' => true,
                    'method' => 'transaction_value_percent',
                    'rate' => 0.2,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'trade_costs.commission.method',
                'trade_costs.commission.rate',
                'trade_costs.transaction_tax.applies_to',
            ]);
    }

    public function test_it_accepts_false_for_both_active_time_boundaries(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            ...$this->cfdProfile(),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quotes('1.100000', '1.200000'),
        ]);

        $response = $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.signal', 'none')
            ->assertJsonPath('order_history', []);
    }

    public function test_it_returns_in_memory_order_history_with_open_close_dates_and_nearest_price(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ]);

        $date = new \DateTimeImmutable('2026-10-08 00:00:00');
        foreach (range(0, 199) as $index) {
            $close = 1.102 - $index * 0.00001;
            MarketPrice::create([
                'pair_id' => $pair->id,
                'price_date' => $date->modify("+{$index} minutes")->format('Y-m-d H:i:s'),
                'timeframe_type_id' => 1,
                'open' => $close,
                'close' => $close,
                'high' => $close + 0.001,
                'low' => $close - 0.001,
                ...$this->quotes((string) $close, (string) $close),
            ]);
        }

        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.110000',
            'bid_open' => '1.109000',
            'bid_high' => '1.110000',
            'bid_low' => '1.100000',
            'bid_close' => '1.109000',
            'ask_open' => '1.111000',
            'ask_high' => '1.112000',
            'ask_low' => '1.101000',
            'ask_close' => '1.111000',
            'low' => '1.100000',
            'high' => '1.110000',
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 10:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.091000',
            'bid_open' => '1.090500',
            'bid_high' => '1.100000',
            'bid_low' => '1.089000',
            'bid_close' => '1.090500',
            'ask_open' => '1.091500',
            'ask_high' => '1.101000',
            'ask_low' => '1.090000',
            'ask_close' => '1.091500',
            'low' => '1.089000',
            'high' => '1.100000',
        ]);

        $response = $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => '09:00',
            'active_until' => '17:00',
        ])
            ->assertOk()
            ->assertJsonPath('order_history.0.open_date', '2026-10-09 09:00:00')
            ->assertJsonPath('order_history.0.close_date', '2026-10-09 10:00:00')
            ->assertJsonPath('order_history.0.close_reason', 'stop_loss')
            ->assertJsonPath('order_history.0.stop_loss', 1.1)
            ->assertJsonPath('order_history.0.closest_to_stop_loss.price', 1.0905)
            ->assertJsonPath('order_history.0.closest_to_stop_loss.source', 'bid_close')
            ->assertJsonPath('order_history.0.closest_to_take_profit.price', 1.1)
            ->assertJsonPath('order_history.0.closest_to_take_profit.source', 'bid_high')
            ->assertJsonPath('initial_capital', 10000);

        $this->assertEqualsWithDelta(9900, $response->json('ending_capital'), 0.00001);
    }

    /**
     * @return array<string, string>
     */
    private function quotes(string $open, string $close): array
    {
        return [
            'bid_open' => $open,
            'bid_high' => $close,
            'bid_low' => $open,
            'bid_close' => $close,
            'ask_open' => $open,
            'ask_high' => $close,
            'ask_low' => $open,
            'ask_close' => $close,
        ];
    }

    /** @return array<string, string> */
    private function cfdProfile(): array
    {
        return [
            'contract_size' => '1',
            'tick_size' => '0.0001',
            'tick_value' => '1',
            'minimum_quantity' => '0.00000001',
            'quantity_step' => '0.00000001',
            'initial_margin_rate' => '0.01',
        ];
    }
}
