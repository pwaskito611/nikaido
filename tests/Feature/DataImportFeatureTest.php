<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\DataImport\Model\DataImportSession;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_data_receiver_is_off_by_default_and_session_token_only_accepts_selected_pair_and_timeframe(): void
    {
        [$pair, $timeframe] = $this->createImportTarget();
        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee('The API receiver is currently off.')
            ->assertSee('Select pair')
            ->assertSee('bid_open', false)
            ->assertSee('ask_close', false)
            ->assertSee('bid/ask fields are optional', false)
            ->assertSee('Pull from data provider')
            ->assertSee('Provider credential')
            ->assertDontSee('Alpha Vantage');
        $this->postJson('/api/data-import/candles', [
            'candles' => [$this->candle()],
        ])->assertUnauthorized();

        $this->post('/workbench/data-import/incoming-sessions', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
        ])->assertRedirect('/workbench/data-import')
            ->assertSessionHas('data_import_token');

        $session = DataImportSession::query()->sole();
        $token = session('data_import_token');
        $this->assertIsString($token);
        $this->assertNotSame($token, $session->token_hash);
        $this->assertSame(hash('sha256', $token), $session->token_hash);
        $this->get('/workbench/data-import')->assertOk()->assertSee($token);

        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$this->candle()],
        ])->assertOk()->assertExactJson(['imported' => 1]);

        $price = MarketPrice::query()->sole();
        $this->assertSame($pair->id, $price->pair_id);
        $this->assertSame($timeframe->id, $price->timeframe_type_id);
        $this->assertNull($price->bid_open);
        $this->assertNull($price->ask_close);

        $updatedCandle = $this->candle();
        $updatedCandle['close'] = 1.13;
        $updatedCandle['bid_open'] = 1.099;
        $updatedCandle['bid_high'] = 1.149;
        $updatedCandle['bid_low'] = 1.089;
        $updatedCandle['bid_close'] = 1.129;
        $updatedCandle['ask_open'] = 1.101;
        $updatedCandle['ask_high'] = 1.151;
        $updatedCandle['ask_low'] = 1.091;
        $updatedCandle['ask_close'] = 1.131;
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$updatedCandle],
        ])->assertOk()->assertExactJson(['imported' => 1]);
        $this->assertSame(1, MarketPrice::query()->count());
        $price = MarketPrice::query()->sole();
        $this->assertSame('1.130000', $price->close);
        $this->assertSame('1.099000', $price->bid_open);
        $this->assertSame('1.131000', $price->ask_close);

        $this->post("/workbench/data-import/incoming-sessions/{$session->id}/stop")
            ->assertRedirect('/workbench/data-import');
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$this->candle()],
        ])->assertUnauthorized();
    }

    public function test_incoming_data_rejects_bad_candles_and_a_second_active_session_for_the_same_selection(): void
    {
        [$pair, $timeframe] = $this->createImportTarget();
        $this->post('/workbench/data-import/incoming-sessions', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
        ])->assertRedirect();
        $token = session('data_import_token');

        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/incoming-sessions', [
                'pair_id' => $pair->id,
                'timeframe_type_id' => $timeframe->id,
            ])->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('pair_id');

        $invalid = $this->candle();
        $invalid['high'] = 1.09;
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$invalid],
        ])->assertUnprocessable()->assertJsonValidationErrors('candles.0.high');
        $this->assertDatabaseCount('market_price', 0);
    }

    public function test_pair_and_timeframe_with_an_import_session_cannot_be_deleted(): void
    {
        [$pair, $timeframe] = $this->createImportTarget();
        $this->post('/workbench/data-import/incoming-sessions', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
        ])->assertRedirect();

        $this->from('/workbench/market-pairs')
            ->delete("/workbench/market-pairs/{$pair->id}")
            ->assertRedirect('/workbench/market-pairs')
            ->assertSessionHasErrors('market_pair');
        $this->from('/workbench/timeframes')
            ->delete("/workbench/timeframes/{$timeframe->id}")
            ->assertRedirect('/workbench/timeframes')
            ->assertSessionHasErrors('timeframe');
        $this->assertDatabaseHas('market_pair', ['id' => $pair->id]);
        $this->assertDatabaseHas('timeframe_type', ['id' => $timeframe->id]);
    }

    public function test_external_provider_imports_selected_daily_ohlc_without_saving_the_entered_credential(): void
    {
        [$pair, $timeframe] = $this->createImportTarget('1d');
        Http::fake([
            'www.alphavantage.co/query*' => Http::response([
                'Meta Data' => [],
                'Time Series FX (Daily)' => [
                    '2026-10-12' => [
                        '1. open' => '1.100000',
                        '2. high' => '1.150000',
                        '3. low' => '1.090000',
                        '4. close' => '1.120000',
                    ],
                ],
            ]),
        ]);

        $this->post('/workbench/data-import/external-provider', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'external_provider_credential' => 'temporary-test-secret',
        ])->assertRedirect('/workbench/data-import')
            ->assertSessionHas('status', 'External provider import complete: 1 candles inserted or updated.')
            ->assertSessionMissing('external_provider_credential');

        Http::assertSent(fn ($request): bool => $request['apikey'] === 'temporary-test-secret'
            && $request['function'] === 'FX_DAILY'
            && $request['from_symbol'] === 'EUR'
            && $request['to_symbol'] === 'USD');

        $price = MarketPrice::query()->sole();
        $this->assertSame('1.100000', $price->open);
        $this->assertNull($price->ask_high);
        $this->assertNull($price->bid_low);
    }

    public function test_provider_hourly_data_is_aggregated_to_the_selected_four_hour_timeframe(): void
    {
        [$pair, $timeframe] = $this->createImportTarget('4h');
        Http::fake([
            'www.alphavantage.co/query*' => Http::response([
                'Time Series FX (60min)' => [
                    '2026-10-12 03:00:00' => ['1. open' => '1.3', '2. high' => '1.4', '3. low' => '1.2', '4. close' => '1.35'],
                    '2026-10-12 02:00:00' => ['1. open' => '1.25', '2. high' => '1.36', '3. low' => '1.21', '4. close' => '1.3'],
                    '2026-10-12 01:00:00' => ['1. open' => '1.2', '2. high' => '1.31', '3. low' => '1.15', '4. close' => '1.25'],
                    '2026-10-12 00:00:00' => ['1. open' => '1.1', '2. high' => '1.25', '3. low' => '1.05', '4. close' => '1.2'],
                    '2026-10-11 23:00:00' => ['1. open' => '1.0', '2. high' => '1.15', '3. low' => '0.95', '4. close' => '1.1'],
                ],
            ]),
        ]);

        $this->post('/workbench/data-import/external-provider', [
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'external_provider_credential' => 'hourly-test-key',
        ])->assertRedirect('/workbench/data-import')
            ->assertSessionHas('status', 'External provider import complete: 2 candles inserted or updated.');

        $candles = MarketPrice::query()->orderBy('price_date')->get();
        $this->assertCount(2, $candles);
        $this->assertSame('2026-10-11 20:00:00', $candles[0]->price_date->format('Y-m-d H:i:s'));
        $this->assertSame('1.000000', $candles[0]->open);
        $this->assertSame('1.150000', $candles[0]->high);
        $this->assertSame('0.950000', $candles[0]->low);
        $this->assertSame('1.100000', $candles[0]->close);
        $this->assertSame('2026-10-12 00:00:00', $candles[1]->price_date->format('Y-m-d H:i:s'));
        $this->assertSame('1.100000', $candles[1]->open);
        $this->assertSame('1.400000', $candles[1]->high);
        $this->assertSame('1.050000', $candles[1]->low);
        $this->assertSame('1.350000', $candles[1]->close);
    }

    public function test_external_provider_error_is_reported_in_the_import_form(): void
    {
        [$pair, $timeframe] = $this->createImportTarget('1d');
        Http::fake([
            'www.alphavantage.co/query*' => Http::response(['Note' => 'API call frequency limit reached.']),
        ]);

        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/external-provider', [
                'pair_id' => $pair->id,
                'timeframe_type_id' => $timeframe->id,
                'external_provider_credential' => 'temporary-test-secret',
            ])->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('external_provider_credential');

        $this->assertFalse(session()->hasOldInput('external_provider_credential'));
        $this->assertDatabaseCount('market_price', 0);
    }

    /**
     * @return array{MarketPair, TimeframeType}
     */
    private function createImportTarget(string $timeframeName = '1h'): array
    {
        $marketType = MarketType::query()->create(['name' => 'Forex']);
        $pair = MarketPair::query()->create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        return [$pair, TimeframeType::query()->create(['name' => $timeframeName])];
    }

    /**
     * @return array{time: string, open: float, high: float, low: float, close: float}
     */
    private function candle(): array
    {
        return [
            'time' => '2026-10-12 10:00:00',
            'open' => 1.1,
            'high' => 1.15,
            'low' => 1.09,
            'close' => 1.12,
        ];
    }
}
