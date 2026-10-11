<?php

namespace Tests\Feature;

use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\MarketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        ]);

        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 08:59:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
        ]);
        $activeCandle = MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.200000',
            'close' => '1.300000',
        ]);

        Http::fake([
            '*/api/strategy/' => Http::response([
                'signal' => 'none',
                'stop_loss' => null,
                'take_profit' => null,
            ], 200),
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'eur',
            'quote' => 'usd',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => '09:00',
            'active_until' => '17:00',
            'excluded_days' => [6, 7],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.candle.id', $activeCandle->id)
            ->assertJsonPath('data.0.result', false)
            ->assertJsonPath('data.0.signal', 'none')
            ->assertJsonPath('order_history', []);
    }

    public function test_it_rejects_invalid_time_configuration(): void
    {
        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => '17:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active_from');
    }

    public function test_it_accepts_false_for_both_active_time_boundaries(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        $this->postJson('/api/engine/specific-pair-test', [
            'base' => 'EUR',
            'quote' => 'USD',
            'from' => '2026-10-09 00:00:00',
            'to' => '2026-10-09 23:59:59',
            'active_from' => false,
            'active_until' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('order_history', []);
    }

    public function test_it_returns_in_memory_order_history_with_open_close_dates_and_nearest_price(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.102000',
            'ask' => '1.101000',
            'low' => '1.100000',
            'high' => '1.110000',
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 10:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.091000',
            'bid' => '1.090500',
            'low' => '1.089000',
            'high' => '1.100000',
        ]);

        Http::fakeSequence()
            ->push([
                'signal' => 'long',
                'stop_loss' => 1.09,
                'take_profit' => 1.12,
            ])
            ->push([
                'signal' => 'none',
                'stop_loss' => null,
                'take_profit' => null,
            ]);

        $this->postJson('/api/engine/specific-pair-test', [
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
            ->assertJsonPath('order_history.0.closest_to_stop_loss.price', 1.0905)
            ->assertJsonPath('order_history.0.closest_to_stop_loss.source', 'bid');
    }
}
