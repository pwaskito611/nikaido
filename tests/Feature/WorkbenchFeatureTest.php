<?php

namespace Tests\Feature;

use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
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
            ->assertSee('SYSTEMATIC TRADING / RESEARCH WORKBENCH')
            ->assertSee('From market data to evaluation')
            ->assertSee('Data import')
            ->assertSee('Market types')
            ->assertSee('Market pairs')
            ->assertSee('Timeframes')
            ->assertSee('Market prices')
            ->assertSee('Market exploration')
            ->assertSee('Run a test')
            ->assertSee('fluctuation stress scenarios and Monte Carlo simulations')
            ->assertSee('Strategy')
            ->assertSee('Engine')
            ->assertDontSee('LIVE DATASET')
            ->assertDontSee('+12.84%');
    }

    public function test_market_exploration_shows_scrollable_naked_candles_for_the_selected_pair_timeframe_and_dates(): void
    {
        $pair = $this->createMarketPair($this->createMarketType());
        $otherPair = MarketPair::create([
            'market_type_id' => $pair->market_type_id,
            'base' => 'GBP',
            'quote' => 'USD',
        ]);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-09 09:00:00',
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.100000',
            'high' => '1.130000',
            'low' => '1.090000',
            'close' => '1.120000',
            ...$this->quoteValues('1.100000', '1.130000', '1.090000', '1.120000'),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-10 23:59:59',
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.120000',
            'high' => '1.140000',
            'low' => '1.110000',
            'close' => '1.130000',
            ...$this->quoteValues('1.120000', '1.140000', '1.110000', '1.130000'),
        ]);
        MarketPrice::create([
            'pair_id' => $otherPair->id,
            'price_date' => '2026-10-10 12:00:00',
            'timeframe_type_id' => $timeframe->id,
            'open' => '2.100000',
            'high' => '2.130000',
            'low' => '2.090000',
            'close' => '2.120000',
            ...$this->quoteValues('2.100000', '2.130000', '2.090000', '2.120000'),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => '2026-10-10 12:00:00',
            'timeframe_type_id' => $otherTimeframe->id,
            'open' => '3.100000',
            'high' => '3.130000',
            'low' => '3.090000',
            'close' => '3.120000',
            ...$this->quoteValues('3.100000', '3.130000', '3.090000', '3.120000'),
        ]);

        $this->get('/workbench/market-exploration')
            ->assertOk()
            ->assertSee('Choose market and date range')
            ->assertSee('data-flatpickr="date"', false)
            ->assertDontSee('data-market-chart', false);

        $this->get("/workbench/market-exploration?pair_id={$pair->id}&timeframe_type_id={$timeframe->id}&from=2026-10-10&to=2026-10-10")
            ->assertOk()
            ->assertSee('data-market-chart', false)
            ->assertSee('data-chart-scroll', false)
            ->assertSee('1 CANDLES')
            ->assertSee('2026-10-10 23:59')
            ->assertDontSee('2026-10-09 09:00')
            ->assertDontSee('2.100000')
            ->assertDontSee('3.100000');
    }

    public function test_market_exploration_validates_required_filters_and_date_order(): void
    {
        $pair = $this->createMarketPair($this->createMarketType());
        $timeframe = TimeframeType::create(['name' => '1h']);

        $this->from('/workbench/market-exploration')
            ->get("/workbench/market-exploration?pair_id={$pair->id}")
            ->assertRedirect('/workbench/market-exploration')
            ->assertSessionHasErrors(['timeframe_type_id', 'from', 'to']);

        $this->from('/workbench/market-exploration')
            ->get("/workbench/market-exploration?pair_id={$pair->id}&timeframe_type_id={$timeframe->id}&from=2026-10-11&to=2026-10-10")
            ->assertRedirect('/workbench/market-exploration')
            ->assertSessionHasErrors('to');
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
            'quantity_unit' => 'lot',
            'contract_size' => '100000.00000000',
        ]);
        $pair = MarketPair::query()->firstOrFail();

        $response->assertRedirect("/workbench/market-pairs/{$pair->id}");
        $this->assertDatabaseHas('market_pair', [
            'id' => $pair->id,
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'lot',
            'contract_size' => '100000.00000000',
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
            'quantity_unit' => 'lot',
            'contract_size' => '1000.00000000',
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
                'quantity_unit' => 'units',
                'contract_size' => '1.00000000',
            ])
            ->assertRedirect('/workbench/market-pairs/create')
            ->assertSessionHasErrors('base');
    }

    public function test_market_pair_units_use_direct_strategy_quantity_without_a_lot_multiplier(): void
    {
        $marketType = $this->createMarketType();

        $this->post('/workbench/market-pairs', [
            'market_type_id' => $marketType->id,
            'base' => 'BTC',
            'quote' => 'USD',
            'quantity_unit' => 'units',
        ])->assertRedirect();

        $pair = MarketPair::query()->where('base', 'BTC')->firstOrFail();

        $this->assertSame('units', $pair->quantity_unit);
        $this->assertSame('1.00000000', $pair->contract_size);
    }

    public function test_market_pair_contract_quantity_requires_and_stores_futures_multiplier(): void
    {
        $marketType = $this->createMarketType();

        $this->from('/workbench/market-pairs/create')
            ->post('/workbench/market-pairs', [
                'market_type_id' => $marketType->id,
                'base' => 'ES',
                'quote' => 'USD',
                'quantity_unit' => 'contract',
            ])
            ->assertRedirect('/workbench/market-pairs/create')
            ->assertSessionHasErrors('contract_size');

        $this->post('/workbench/market-pairs', [
            'market_type_id' => $marketType->id,
            'base' => 'ES',
            'quote' => 'USD',
            'quantity_unit' => 'contract',
            'contract_size' => '50.00000000',
        ])->assertRedirect();

        $pair = MarketPair::query()->where('base', 'ES')->firstOrFail();
        $this->assertSame('contract', $pair->quantity_unit);
        $this->assertSame('50.00000000', $pair->contract_size);
        $this->get("/workbench/market-pairs/{$pair->id}")
            ->assertOk()
            ->assertSee('Contracts')
            ->assertSee('50.00000000 quote-currency units per price point per contract');
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

    public function test_market_price_can_be_created_viewed_updated_deleted_and_filtered(): void
    {
        $marketType = $this->createMarketType();
        $pair = $this->createMarketPair($marketType);
        $otherPair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'GBP',
            'quote' => 'USD',
        ]);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);

        $this->get('/workbench/market-prices/create')
            ->assertOk()
            ->assertSee('data-flatpickr="datetime"', false);

        $createResponse = $this->post('/workbench/market-prices', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'price_date' => '2026-10-09T09:00',
            'open' => '1.100000',
            'high' => '1.120000',
            'low' => '1.090000',
            'close' => '1.110000',
            'bid_open' => '1.099000',
            'bid_high' => '1.119000',
            'bid_low' => '1.089000',
            'bid_close' => '1.109000',
            'ask_open' => '1.101000',
            'ask_high' => '1.121000',
            'ask_low' => '1.091000',
            'ask_close' => '1.111000',
            'price_at_8' => '1.105000',
        ]);

        $price = MarketPrice::query()
            ->where('pair_id', $pair->id)
            ->where('timeframe_type_id', $timeframe->id)
            ->firstOrFail();
        $createResponse->assertRedirect("/workbench/market-prices/{$price->id}");
        $this->assertDatabaseHas('market_price', [
            'id' => $price->id,
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'price_date' => '2026-10-09 09:00:00',
        ]);

        foreach (range(0, 24) as $minute) {
            MarketPrice::create([
                'pair_id' => $pair->id,
                'timeframe_type_id' => $timeframe->id,
                'price_date' => sprintf('2026-10-09 10:%02d:00', $minute),
                'close' => '1.250000',
                ...$this->quoteValues('1.250000', '1.250000', '1.250000', '1.250000'),
            ]);
        }
        MarketPrice::create([
            'pair_id' => $otherPair->id,
            'timeframe_type_id' => $timeframe->id,
            'price_date' => '2026-10-09 10:00:00',
            'close' => '1.200000',
            ...$this->quoteValues('1.200000', '1.200000', '1.200000', '1.200000'),
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'timeframe_type_id' => $otherTimeframe->id,
            'price_date' => '2026-10-09 11:00:00',
            'close' => '1.300000',
            ...$this->quoteValues('1.300000', '1.300000', '1.300000', '1.300000'),
        ]);

        $this->from('/workbench/market-prices/create')
            ->post('/workbench/market-prices', [
                'pair_id' => $pair->id,
                'timeframe_type_id' => $timeframe->id,
                'price_date' => '2026-10-09T09:00',
                'close' => '1.110000',
                ...$this->quoteValues('1.100000', '1.120000', '1.090000', '1.109000'),
            ])
            ->assertRedirect('/workbench/market-prices/create')
            ->assertSessionHasErrors('price_date');

        $this->get('/workbench/market-prices')
            ->assertOk()
            ->assertSee('Market prices')
            ->assertSee('1h')
            ->assertSee('4h');
        $this->get("/workbench/market-prices?pair_id={$pair->id}&timeframe_type_id={$timeframe->id}")
            ->assertOk()
            ->assertSee('page=2')
            ->assertDontSee('1.110000')
            ->assertDontSee('1.200000')
            ->assertDontSee('1.300000');
        $this->get("/workbench/market-prices?pair_id={$pair->id}&timeframe_type_id={$timeframe->id}&page=2")
            ->assertOk()
            ->assertSee('1.110000')
            ->assertDontSee('1.200000')
            ->assertDontSee('1.300000');

        $this->get("/workbench/market-prices/{$price->id}")
            ->assertOk()
            ->assertSee('Bid / Ask OHLC')
            ->assertSee('1.119000');
        $this->get("/workbench/market-prices/{$price->id}/edit")
            ->assertOk()
            ->assertSee('name="bid_open"', false)
            ->assertSee('data-flatpickr="datetime"', false);

        $this->put("/workbench/market-prices/{$price->id}", [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'price_date' => '2026-10-09T09:00',
            'open' => '1.100000',
            'high' => '1.120000',
            'low' => '1.090000',
            'close' => '1.115000',
            'bid_open' => '1.099000',
            'bid_high' => '1.119000',
            'bid_low' => '1.089000',
            'bid_close' => '1.114000',
            'ask_open' => '1.101000',
            'ask_high' => '1.121000',
            'ask_low' => '1.091000',
            'ask_close' => '1.116000',
        ])->assertRedirect("/workbench/market-prices/{$price->id}");
        $this->assertDatabaseHas('market_price', ['id' => $price->id, 'close' => '1.115000']);

        $this->delete("/workbench/market-prices/{$price->id}")
            ->assertRedirect('/workbench/market-prices');
        $this->assertDatabaseMissing('market_price', ['id' => $price->id]);
    }

    public function test_test_setup_redirects_to_backtest_results_after_running_engine(): void
    {
        $pair = $this->createMarketPair($this->createMarketType());
        $timeframe = TimeframeType::create(['name' => '1h']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);
        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('data-flatpickr="datetime"', false)
            ->assertSee('data-flatpickr="time"', false);
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
            ->assertSee('Strategy supplies the requested quantity using each pair')
            ->assertDontSee('name="trade_size"', false)
            ->assertSee('value="10000.00"', false);

        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('1h')
            ->assertSee('4h');

        $this->post('/workbench/tests', [
            'market_pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'initial_capital' => '10000.00',
            'from' => '2026-10-09T00:00',
            'to' => '2026-10-09T23:59',
            'active_from' => '09:00',
            'active_until' => '17:00',
        ])->assertRedirect('/workbench/tests/results');

        $this->get('/workbench/tests/results')
            ->assertOk()
            ->assertSee('EUR/USD')
            ->assertSee('1h')
            ->assertSee('Execution assumptions')
            ->assertSee('Order history')
            ->assertSee('stop loss')
            ->assertSee('ENDING CAPITAL')
            ->assertSee('PROFIT FACTOR')
            ->assertSee('AVERAGE DRAWDOWN')
            ->assertSee('MEDIAN DRAWDOWN')
            ->assertSee('MAXIMUM DRAWDOWN')
            ->assertSee('strategy quantity')
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
            ->assertSee('1 ORDERS');

        $this->get('/workbench/tests/results?stress_x=0.5')
            ->assertOk()
            ->assertSee('Fluctuation Stress Test')
            ->assertSee('STRESS SCENARIO')
            ->assertSee('FULL BASELINE')
            ->assertSee('Stress-test exit count')
            ->assertSee('Additional stress factor')
            ->assertSee('additional adverse move = x × excursion')
            ->assertSee('Stress parameter x = 0.50');

        $this->get('/workbench/tests/results?monte_carlo=1&simulation_count=20&trades_per_simulation=2&method=bootstrap&mc_initial_capital=10000&risk_model=fixed_monetary_pl&drawdown_threshold=10&random_seed=42')
            ->assertOk()
            ->assertSee('Monte Carlo Simulation')
            ->assertSee('20 simulations')
            ->assertSee('FINAL EQUITY')
            ->assertSee('MAX DRAWDOWN DISTRIBUTION');

        $this->assertSame('stop_loss', session('backtestResult')['order_history'][0]['close_reason']);
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

    public function test_backtest_setup_requires_positive_capital(): void
    {
        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', [
                'market_pair_id' => '1',
                'timeframe_type_id' => '1',
                'initial_capital' => '0',
                'from' => '2026-10-09T00:00',
                'to' => '2026-10-09T23:59',
                'active_from' => '09:00',
                'active_until' => '17:00',
            ])
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors(['initial_capital']);
    }

    public function test_backtest_setup_exposes_optional_execution_configuration_and_validates_enabled_values(): void
    {
        $marketType = $this->createMarketType();
        $this->createMarketPair($marketType);
        TimeframeType::create(['name' => '1h']);

        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('Fixed slippage')
            ->assertSee('Synthetic partial fills')
            ->assertSee('not a liquidity simulation')
            ->assertSee('slippage_enabled')
            ->assertSee('partial_fill_enabled');

        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', [
                'test_type' => 'specific_pair',
                'market_pair_id' => '1',
                'timeframe_type_id' => '1',
                'initial_capital' => '10000',
                'from' => '2026-10-09T00:00',
                'to' => '2026-10-09T23:59',
                'active_from' => '09:00',
                'active_until' => '17:00',
                'slippage_enabled' => '1',
                'slippage_pips' => '1',
                'partial_fill_enabled' => '1',
                'partial_fill_ratio' => '0',
            ])
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors('partial_fill_ratio');
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
            'quantity_unit' => 'lot',
            'contract_size' => '100000.00000000',
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
