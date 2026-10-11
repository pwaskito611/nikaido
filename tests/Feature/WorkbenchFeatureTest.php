<?php

namespace Tests\Feature;

use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\MarketType;
use App\Engine\Models\TimeframeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkbenchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_links_to_market_crud_and_test_setup(): void
    {
        $this->get('/')
            ->assertRedirect('/workbench');

        $this->get('/workbench')
            ->assertOk()
            ->assertSee('Market types')
            ->assertSee('Market pairs')
            ->assertSee('Timeframes')
            ->assertSee('Run a test');
    }

    public function test_timeframe_can_be_created_viewed_updated_and_deleted(): void
    {
        $response = $this->post('/workbench/timeframes', ['name' => '1h']);
        $timeframe = TimeframeType::query()->where('name', '1h')->firstOrFail();
        $response->assertRedirect("/workbench/timeframes/{$timeframe->id}");

        $this->get("/workbench/timeframes/{$timeframe->id}")
            ->assertOk()
            ->assertSee('1h');

        $this->get("/workbench/timeframes/{$timeframe->id}/edit")
            ->assertOk()
            ->assertSee('value="1h"', false);

        $this->put("/workbench/timeframes/{$timeframe->id}", ['name' => '2h'])
            ->assertRedirect("/workbench/timeframes/{$timeframe->id}");
        $this->assertDatabaseHas('timeframe_type', ['id' => $timeframe->id, 'name' => '2h']);

        $this->delete("/workbench/timeframes/{$timeframe->id}")
            ->assertRedirect('/workbench/timeframes');
        $this->assertDatabaseMissing('timeframe_type', ['id' => $timeframe->id]);
    }

    public function test_timeframe_cannot_be_deleted_while_used_by_market_prices(): void
    {
        $timeframe = TimeframeType::create(['name' => '1h']);
        $pair = $this->createMarketPair($this->createMarketType());
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quoteValues('1.100000', '1.200000', '1.100000', '1.200000'),
        ]);

        $this->from('/workbench/timeframes')
            ->delete("/workbench/timeframes/{$timeframe->id}")
            ->assertRedirect('/workbench/timeframes')
            ->assertSessionHasErrors('timeframe');

        $this->assertDatabaseHas('timeframe_type', ['id' => $timeframe->id]);
    }

    public function test_market_type_can_be_created_viewed_updated_and_deleted(): void
    {
        $this->post('/workbench/market-types', ['name' => 'Forex'])
            ->assertRedirect('/workbench/market-types');
        $marketType = MarketType::query()->where('name', 'Forex')->firstOrFail();

        $this->get("/workbench/market-types/{$marketType->id}")
            ->assertOk()
            ->assertSee('Forex');

        $this->get("/workbench/market-types/{$marketType->id}/edit")
            ->assertOk()
            ->assertSee('value="Forex"', false);

        $this->put("/workbench/market-types/{$marketType->id}", ['name' => 'Crypto'])
            ->assertRedirect('/workbench/market-types');
        $this->assertDatabaseHas('market_type', ['id' => $marketType->id, 'name' => 'Crypto']);

        $this->delete("/workbench/market-types/{$marketType->id}")
            ->assertRedirect('/workbench/market-types');
        $this->assertDatabaseMissing('market_type', ['id' => $marketType->id]);
    }

    public function test_market_type_cannot_be_deleted_while_it_has_market_pairs(): void
    {
        $marketType = $this->createMarketType();
        $this->createMarketPair($marketType);

        $this->from('/workbench/market-types')
            ->delete("/workbench/market-types/{$marketType->id}")
            ->assertRedirect('/workbench/market-types')
            ->assertSessionHasErrors('market_type');

        $this->assertDatabaseHas('market_type', ['id' => $marketType->id]);
    }

    public function test_market_pair_can_be_created_viewed_updated_and_deleted(): void
    {
        $marketType = $this->createMarketType();
        $response = $this->post('/workbench/market-pairs', [
            'market_type_id' => $marketType->id,
            'base' => 'eur',
            'quote' => 'usd',
        ]);
        $pair = MarketPair::query()->firstOrFail();

        $response->assertRedirect("/workbench/market-pairs/{$pair->id}");
        $this->assertDatabaseHas('market_pair', [
            'id' => $pair->id,
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        $this->get("/workbench/market-pairs/{$pair->id}")
            ->assertOk()
            ->assertSee('EUR')
            ->assertSee('USD')
            ->assertSee('Forex');

        $this->get("/workbench/market-pairs/{$pair->id}/edit")
            ->assertOk()
            ->assertSee('value="EUR"', false);

        $this->put("/workbench/market-pairs/{$pair->id}", [
            'market_type_id' => $marketType->id,
            'base' => 'GBP',
            'quote' => 'USD',
        ])->assertRedirect("/workbench/market-pairs/{$pair->id}");
        $this->assertDatabaseHas('market_pair', ['id' => $pair->id, 'base' => 'GBP']);

        $this->delete("/workbench/market-pairs/{$pair->id}")
            ->assertRedirect('/workbench/market-pairs');
        $this->assertDatabaseMissing('market_pair', ['id' => $pair->id]);
    }

    public function test_market_pair_rejects_duplicate_symbols_within_a_market_type(): void
    {
        $marketType = $this->createMarketType();
        $this->createMarketPair($marketType);

        $this->from('/workbench/market-pairs/create')
            ->post('/workbench/market-pairs', [
                'market_type_id' => $marketType->id,
                'base' => 'eur',
                'quote' => 'usd',
            ])
            ->assertRedirect('/workbench/market-pairs/create')
            ->assertSessionHasErrors('base');
    }

    public function test_market_pair_cannot_be_deleted_while_it_has_price_history(): void
    {
        $pair = $this->createMarketPair($this->createMarketType());
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            ...$this->quoteValues('1.100000', '1.200000', '1.100000', '1.200000'),
        ]);

        $this->from('/workbench/market-pairs')
            ->delete("/workbench/market-pairs/{$pair->id}")
            ->assertRedirect('/workbench/market-pairs')
            ->assertSessionHasErrors('market_pair');

        $this->assertDatabaseHas('market_pair', ['id' => $pair->id]);
    }

    public function test_test_setup_redirects_to_backtest_results_after_running_engine(): void
    {
        $pair = $this->createMarketPair($this->createMarketType());
        $timeframe = TimeframeType::create(['name' => '1h']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);
        $date = new \DateTimeImmutable('2026-10-08 00:00:00');
        foreach (range(0, 199) as $index) {
            $close = 1.102 - $index * 0.00001;
            MarketPrice::create([
                'pair_id' => $pair->id,
                'price_date' => $date->modify("+{$index} minutes")->format('Y-m-d H:i:s'),
                'timeframe_type_id' => $timeframe->id,
                'open' => $close,
                'close' => $close,
                'high' => $close + 0.001,
                'low' => $close - 0.001,
                ...$this->quoteValues($close, $close + 0.001, $close - 0.001, $close),
            ]);
        }
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.100000',
            'close' => '1.110000',
            'low' => '1.100000',
            'high' => '1.110000',
            'bid_open' => '1.100000',
            'bid_high' => '1.110000',
            'bid_low' => '1.100000',
            'bid_close' => '1.110000',
            'ask_open' => '1.101000',
            'ask_high' => '1.112000',
            'ask_low' => '1.101000',
            'ask_close' => '1.111000',
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 10:00:00',
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.100000',
            'close' => '1.091000',
            'low' => '1.089000',
            'high' => '1.100000',
            'bid_open' => '1.099500',
            'bid_high' => '1.100000',
            'bid_low' => '1.089000',
            'bid_close' => '1.090500',
            'ask_open' => '1.100500',
            'ask_high' => '1.101000',
            'ask_low' => '1.090000',
            'ask_close' => '1.091500',
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:30:00',
            'timeframe_type_id' => $otherTimeframe->id,
            'open' => '1.100000',
            'close' => '1.101000',
            'low' => '1.099000',
            'high' => '1.103000',
            ...$this->quoteValues('1.100000', '1.103000', '1.099000', '1.101000'),
        ]);

        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('EUR/USD')
            ->assertSee('Initial capital')
            ->assertSee('Risk per trade (%)')
            ->assertSee('value="10000.00"', false)
            ->assertSee('value="1.00"', false);

        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('1h')
            ->assertSee('4h');

        $this->post('/workbench/tests', [
            'market_pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'initial_capital' => '10000.00',
            'risk_per_trade_percent' => '1.00',
            'from' => '2026-10-09T00:00',
            'to' => '2026-10-09T23:59',
            'active_from' => '09:00',
            'active_until' => '17:00',
        ])->assertRedirect('/workbench/tests/results');

        $this->get('/workbench/tests/results')
            ->assertOk()
            ->assertSee('EUR/USD')
            ->assertSee('1h')
            ->assertSee('Order history')
            ->assertSee('stop loss')
            ->assertSee('ENDING CAPITAL')
            ->assertSee('RISK PER TRADE')
            ->assertSee('1.090500')
            ->assertSee('Candle decisions')
            ->assertSee('NEAREST TO TP')
            ->assertSee('Capital movement');

        $this->get('/workbench/tests/results?clusters=2&cluster=1')
            ->assertOk()
            ->assertSee('Cluster 1 analysis')
            ->assertSee('1 ORDERS');

        $this->get('/workbench/tests/results?clusters=2&cluster=2')
            ->assertOk()
            ->assertSee('Cluster 2 analysis')
            ->assertSee('0 ORDERS');
    }

    public function test_result_page_redirects_to_test_setup_when_there_is_no_result(): void
    {
        $this->get('/workbench/tests/results')
            ->assertRedirect('/workbench/tests/create');
    }

    public function test_backtest_results_can_be_paged_and_viewed_by_chronological_clusters(): void
    {
        $results = [];
        foreach (range(1, 60) as $index) {
            $results[] = [
                'candle' => [
                    'price_date' => sprintf('2026-10-09 %02d:%02d:00', intdiv($index - 1, 60), ($index - 1) % 60),
                    'close' => '1.100000',
                ],
                'result' => false,
                'signal' => 'none',
                'stop_loss' => null,
                'take_profit' => null,
            ];
        }
        session()->put('backtestResult', [
            'pair' => 'EUR/USD',
            'timeframe' => '1h',
            'from' => '2026-10-09',
            'to' => '2026-10-09',
            'initial_capital' => 10000.0,
            'risk_per_trade_percent' => 1.0,
            'ending_capital' => 10000.0,
            'results' => $results,
            'order_history' => [],
        ]);

        $this->get('/workbench/tests/results?page=2')
            ->assertOk()
            ->assertSee('60 DECISIONS')
            ->assertSee('2026-10-09 00:50:00')
            ->assertDontSee('2026-10-09 00:49:00');

        $this->get('/workbench/tests/results?clusters=2&cluster=2')
            ->assertOk()
            ->assertSee('Cluster 2 analysis')
            ->assertSee('30 DECISIONS')
            ->assertSee('2026-10-09 00:30:00')
            ->assertDontSee('2026-10-09 00:00:00');
    }

    public function test_backtest_setup_requires_positive_capital_and_risk_up_to_one_hundred_percent(): void
    {
        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', [
                'market_pair_id' => '1',
                'timeframe_type_id' => '1',
                'initial_capital' => '0',
                'risk_per_trade_percent' => '100.01',
                'from' => '2026-10-09T00:00',
                'to' => '2026-10-09T23:59',
                'active_from' => '09:00',
                'active_until' => '17:00',
            ])
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors(['initial_capital', 'risk_per_trade_percent']);
    }

    private function createMarketType(): MarketType
    {
        return MarketType::create(['name' => 'Forex']);
    }

    private function createMarketPair(MarketType $marketType): MarketPair
    {
        return MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
    }

    /**
     * @return array<string, string|float>
     */
    private function quoteValues(string|float $open, string|float $high, string|float $low, string|float $close): array
    {
        return [
            'bid_open' => $open,
            'bid_high' => $high,
            'bid_low' => $low,
            'bid_close' => $close,
            'ask_open' => $open,
            'ask_high' => $high,
            'ask_low' => $low,
            'ask_close' => $close,
        ];
    }
}
