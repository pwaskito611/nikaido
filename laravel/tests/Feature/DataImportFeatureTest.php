<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\DataImport\Model\DataImportSession;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('PRICE DATA · OHLCV')
            ->assertSee('FUNDAMENTAL DATA · INDICATORS')
            ->assertSee('Import market candles')
            ->assertSee('Import economic/company observations')
            ->assertSee('This receiver is scoped to one Fundamental entity—not a market pair or timeframe.')
            ->assertSee('/api/data-import/fundamentals/observations')
            ->assertSee('bid_open', false)
            ->assertSee('ask_close', false)
            ->assertSee('Bid/ask fields are optional.', false)
            ->assertSee('volume_type', false)
            ->assertSee('Start candle receiver')
            ->assertDontSee('external_provider_credential')
            ->assertDontSee('Pull from data provider');
        $this->post('/workbench/data-import/external-provider', [])->assertNotFound();
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

        $tradedVolumeCandle = $this->candle();
        $tradedVolumeCandle['volume'] = 2500.5;
        $tradedVolumeCandle['volume_type'] = 'traded';
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$tradedVolumeCandle],
        ])->assertOk()->assertExactJson(['imported' => 1]);

        $price = MarketPrice::query()->sole();
        $this->assertSame($pair->id, $price->pair_id);
        $this->assertSame($timeframe->id, $price->timeframe_type_id);
        $this->assertNull($price->bid_open);
        $this->assertNull($price->ask_close);
        $this->assertSame('2500.50000000', $price->volume);
        $this->assertSame('traded', $price->volume_type);

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
        $updatedCandle['volume'] = 900;
        $updatedCandle['volume_type'] = 'tick';
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$updatedCandle],
        ])->assertOk()->assertExactJson(['imported' => 1]);
        $this->assertSame(1, MarketPrice::query()->count());
        $price = MarketPrice::query()->sole();
        $this->assertSame('1.130000', $price->close);
        $this->assertSame('1.099000', $price->bid_open);
        $this->assertSame('1.131000', $price->ask_close);
        $this->assertSame('900.00000000', $price->volume);
        $this->assertSame('tick', $price->volume_type);

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
        $invalidVolume = $this->candle();
        $invalidVolume['volume'] = -1;
        $invalidVolume['volume_type'] = 'traded';
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$invalidVolume],
        ])->assertUnprocessable()->assertJsonValidationErrors('candles.0.volume');
        $overPrecisionVolume = $this->candle();
        $overPrecisionVolume['volume'] = '10.123456789';
        $overPrecisionVolume['volume_type'] = 'traded';
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$overPrecisionVolume],
        ])->assertUnprocessable()->assertJsonValidationErrors('candles.0.volume');
        $untypedVolume = $this->candle();
        $untypedVolume['volume'] = 10;
        $this->withToken($token)->postJson('/api/data-import/candles', [
            'candles' => [$untypedVolume],
        ])->assertUnprocessable()->assertJsonValidationErrors('candles.0.volume_type');
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

    public function test_data_import_page_starts_and_stops_a_distinct_fundamental_observation_receiver(): void
    {
        $type = MarketType::query()->create(['name' => 'Forex']);
        $pair = MarketPair::query()->create([
            'market_type_id' => $type->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $fundamental = Fundamental::query()->create(['name' => 'United States Economy', 'category' => 'Economy']);
        $fundamental->marketPairs()->attach($pair->id);

        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee('United States Economy')
            ->assertSee('Fundamental entity')
            ->assertSee('No pair/timeframe is selected here.')
            ->assertSee('Request body · dated observations, not OHLCV candles');

        $this->post('/workbench/data-import/fundamental-sessions', [
            'fundamental_id' => $fundamental->id,
        ])->assertRedirect('/workbench/data-import')
            ->assertSessionHas('fundamental_import_token');

        $session = FundamentalImportSession::query()->sole();
        $token = session('fundamental_import_token');
        $this->assertNotSame($token, $session->token_hash);
        $this->assertSame(hash('sha256', $token), $session->token_hash);

        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee('FUNDAMENTAL TOKEN · copy now')
            ->assertSee($token)
            ->assertSee('/api/data-import/fundamentals/observations')
            ->assertSee('United States Economy · Economy · RECEIVER ACTIVE');

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [[
                'code' => 'CPI',
                'name' => 'Consumer Price Index',
                'datetime' => '2024-09-01 00:00:00',
                'released_at' => '2024-10-10 08:30:00',
                'value' => 1.1,
            ]],
        ])->assertOk()->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('fundamental_contents', [
            'fundamental_id' => $fundamental->id,
            'code' => 'CPI',
        ]);

        $this->post("/workbench/data-import/fundamental-sessions/{$fundamental->id}/{$session->id}/stop")
            ->assertRedirect('/workbench/data-import');
        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [$this->apiObservation()],
        ])->assertUnauthorized();
        $this->assertDatabaseCount('fundamental_contents', 1);
    }

    public function test_fundamental_import_form_rejects_missing_or_unknown_entity(): void
    {
        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/fundamental-sessions', [])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('fundamental_id');

        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/fundamental-sessions', ['fundamental_id' => 99999])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('fundamental_id');

        $this->assertDatabaseCount('fundamental_import_sessions', 0);
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

    /** @return array{code: string, name: string, datetime: string, released_at: string, value: float} */
    private function apiObservation(): array
    {
        return [
            'code' => 'CPI',
            'name' => 'Consumer Price Index',
            'datetime' => '2024-09-01 00:00:00',
            'released_at' => '2024-10-10 08:30:00',
            'value' => 1.1,
        ];
    }
}
