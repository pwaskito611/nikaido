<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\Fundamentals\Action\LoadFundamentalDataForBacktestAction;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalContent;
use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use App\Platform\Strategy\Strategy;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nikaido\Strategy\Enums\StrategySignal;
use Nikaido\Strategy\Models\StrategyDecision;
use Nikaido\Strategy\Models\StrategyExecutionContext;
use Tests\TestCase;

class FundamentalFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_fundamental_and_indicator_observations_support_crud_and_pair_relationships(): void
    {
        $type = MarketType::create(['name' => 'Equities']);
        $pair = $this->pair($type, 'AAPL', 'USD');

        $this->post('/workbench/fundamentals', [
            'name' => 'Apple Inc.',
            'description' => 'Company fundamentals',
            'category' => 'Company',
            'pair_ids_present' => '1',
            'pair_ids' => [$pair->id],
        ])->assertRedirect();
        $fundamental = Fundamental::query()->where('name', 'Apple Inc.')->firstOrFail();

        $this->assertDatabaseHas('fundamental_related', [
            'fundamental_id' => $fundamental->id,
            'pair_id' => $pair->id,
        ]);

        $this->post("/workbench/fundamentals/{$fundamental->id}/contents", [
            'code' => 'revenue',
            'name' => 'Revenue',
            'value' => '{"amount": 100, "currency": "USD"}',
            'datetime' => '2026-08-01T00:00',
            'released_at' => '2026-08-15T12:30',
        ])->assertRedirect("/workbench/fundamentals/{$fundamental->id}");
        $content = FundamentalContent::query()->firstOrFail();

        $this->assertSame('REVENUE', $content->code);
        $this->assertSame(['amount' => 100, 'currency' => 'USD'], $content->value);
        $this->assertSame('2026-08-01 00:00:00', $content->datetime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 12:30:00', $content->released_at->format('Y-m-d H:i:s'));
        $this->get("/workbench/fundamentals/{$fundamental->id}")
            ->assertOk()
            ->assertSee('Revenue')
            ->assertSee('2026-08-01');

        $this->put("/workbench/fundamentals/{$fundamental->id}/contents/{$content->id}", [
            'code' => 'EPS',
            'name' => 'Earnings per share',
            'value' => '2.75',
            'datetime' => '2026-09-01T00:00',
            'released_at' => '2026-09-10T09:00',
        ])->assertRedirect("/workbench/fundamentals/{$fundamental->id}");
        $this->assertDatabaseHas('fundamental_contents', [
            'id' => $content->id,
            'code' => 'EPS',
            'value' => '2.75',
        ]);

        $this->delete("/workbench/fundamentals/{$fundamental->id}/contents/{$content->id}")
            ->assertRedirect("/workbench/fundamentals/{$fundamental->id}");
        $this->assertDatabaseMissing('fundamental_contents', ['id' => $content->id]);

        $this->put("/workbench/fundamentals/{$fundamental->id}", [
            'name' => 'Apple',
            'description' => 'Updated entity',
            'category' => 'Company',
            'pair_ids_present' => '1',
        ])->assertRedirect("/workbench/fundamentals/{$fundamental->id}");
        $this->assertDatabaseMissing('fundamental_related', ['fundamental_id' => $fundamental->id]);

        $this->delete("/workbench/fundamentals/{$fundamental->id}")->assertRedirect('/workbench/fundamentals');
        $this->assertDatabaseMissing('fundamentals', ['id' => $fundamental->id]);
    }

    public function test_fundamental_requests_reject_duplicate_pairs_invalid_json_and_duplicate_observation_periods(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $pair = $this->pair($type, 'EUR', 'USD');

        $this->from('/workbench/fundamentals/create')
            ->post('/workbench/fundamentals', [
                'name' => 'United States Economy',
                'pair_ids_present' => '1',
                'pair_ids' => [$pair->id, $pair->id],
            ])
            ->assertRedirect('/workbench/fundamentals/create')
            ->assertSessionHasErrors('pair_ids.1');

        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $valid = [
            'code' => 'CPI',
            'name' => 'Consumer Price Index',
            'value' => '13',
            'datetime' => '2026-08-01T00:00',
            'released_at' => '',
        ];

        $this->from("/workbench/fundamentals/{$fundamental->id}/contents/create")
            ->post("/workbench/fundamentals/{$fundamental->id}/contents", [...$valid, 'value' => '{broken'])
            ->assertRedirect("/workbench/fundamentals/{$fundamental->id}/contents/create")
            ->assertSessionHasErrors('value');
        $this->post("/workbench/fundamentals/{$fundamental->id}/contents", $valid)->assertRedirect();
        $this->from("/workbench/fundamentals/{$fundamental->id}/contents/create")
            ->post("/workbench/fundamentals/{$fundamental->id}/contents", $valid)
            ->assertRedirect("/workbench/fundamentals/{$fundamental->id}/contents/create")
            ->assertSessionHasErrors('datetime');
    }

    public function test_database_constraints_reject_invalid_foreign_keys_and_duplicate_pair_relationships(): void
    {
        $fundamental = Fundamental::create(['name' => 'US Economy']);
        $type = MarketType::create(['name' => 'Forex']);
        $pair = $this->pair($type, 'EUR', 'USD');
        $fundamental->marketPairs()->attach($pair->id);

        try {
            DB::table('fundamental_related')->insert([
                'fundamental_id' => $fundamental->id,
                'pair_id' => $pair->id,
            ]);
            $this->fail('The unique fundamental/pair constraint must reject duplicate relations.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        try {
            DB::table('fundamental_contents')->insert([
                'fundamental_id' => 99999,
                'code' => 'GDP',
                'name' => 'GDP',
                'value' => '1.2',
                'datetime' => '2026-01-01 00:00:00',
            ]);
            $this->fail('The content foreign key must reject a missing fundamental.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_point_in_time_fundamental_data_is_related_and_loaded_once_before_replay(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $pair = $this->pair($type, 'EUR', 'USD');
        $otherPair = $this->pair($type, 'GBP', 'USD');
        $economy = Fundamental::create(['name' => 'United States Economy', 'category' => 'Economy']);
        $unrelated = Fundamental::create(['name' => 'United Kingdom Economy', 'category' => 'Economy']);
        $economy->marketPairs()->attach($pair->id);
        $unrelated->marketPairs()->attach($otherPair->id);

        $this->content($economy, 'CPI', '2019-10-01', '2019-10-10 00:00:00', 1.8);
        $this->content($economy, 'CPI', '2020-01-01', '2020-01-10 00:00:00', 2.1);
        $this->content($economy, 'CPI', '2020-02-01', '2020-03-10 00:00:00', 2.4);
        $this->content($economy, 'GDP', '2020-01-01', null, ['rate' => 1.5]);
        $this->content($unrelated, 'CPI', '2020-01-01', '2020-01-02 00:00:00', 9.9);
        $this->price($pair, $timeframe, '2020-02-10 00:00:00');
        $this->price($pair, $timeframe, '2020-03-10 00:00:00');

        $observationsQueried = 0;
        DB::listen(static function ($query) use (&$observationsQueried): void {
            if (str_contains($query->sql, 'fundamental_contents')) {
                $observationsQueried++;
            }
        });

        $strategy = new class((int) $economy->id) extends Strategy
        {
            /** @var list<?float> */
            public array $latestCpiByCandle = [];

            public ?StrategyExecutionContext $context = null;

            public function __construct(private readonly int $fundamentalId) {}

            public function execute(iterable $candles, StrategyExecutionContext $context): iterable
            {
                $this->context = $context;
                $decisions = [];
                foreach ($candles as $index => $candle) {
                    $economy = $context->fundamentals()->get($this->fundamentalId);
                    $latest = $economy?->latestAvailable('CPI', $candle->price_date);
                    $this->latestCpiByCandle[] = $latest === null ? null : (float) $latest->value;
                    $decisions[$index] = new StrategyDecision(StrategySignal::None, null, null);
                }

                return $decisions;
            }
        };
        $config = new SpecificPairTestConfig(
            base: $pair->base,
            quote: $pair->quote,
            from: new DateTimeImmutable('2020-02-10 00:00:00'),
            to: new DateTimeImmutable('2020-03-10 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: $timeframe->id,
            marketPairId: $pair->id,
        );

        $result = (new RunSpecificPairBacktestAction(
            app(MarketPairRepository::class),
            new SpecificPairBacktestProcess($strategy),
            app(LoadFundamentalDataForBacktestAction::class),
        ))->execute($config);

        $this->assertCount(0, $result->orders);
        $this->assertSame([2.1, 2.4], $strategy->latestCpiByCandle);
        $this->assertSame(1, count($strategy->context->fundamentals()->all()));
        $this->assertNull($strategy->context->fundamentals()->get(999));
        $this->assertCount(1, $strategy->context->fundamentals()->get($economy->id)->observations(
            'CPI',
            new DateTimeImmutable('2020-02-15'),
        ));
        $this->assertFalse($strategy->context->fundamentals()->get($economy->id)->has('GDP', new DateTimeImmutable('2020-04-01')));
        $this->assertNull($strategy->context->fundamentals()->get($economy->id)->valueAt(
            'CPI',
            new DateTimeImmutable('2020-02-01'),
            new DateTimeImmutable('2020-02-15'),
        ));
        $this->assertSame(1, $observationsQueried, 'Fundamental observations should be fetched once, not once per candle.');
    }

    public function test_unreleased_fundamentals_are_never_passed_to_strategy_context(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $pair = $this->pair($type, 'EUR', 'USD');
        $fundamental = Fundamental::create(['name' => 'Unreleased Economic Series']);
        $fundamental->marketPairs()->attach($pair->id);
        $this->content($fundamental, 'CPI', '2020-01-01', null, 2.1);

        $data = app(LoadFundamentalDataForBacktestAction::class)->execute(
            $pair->id,
            new DateTimeImmutable('2020-01-01'),
            new DateTimeImmutable('2020-02-01'),
        );

        $this->assertTrue($data->has($fundamental->id));
        $entity = $data->get($fundamental->id);
        $this->assertFalse($entity->has('CPI', new DateTimeImmutable('2020-02-01')));
        $this->assertNull($entity->latestAvailable('CPI', new DateTimeImmutable('2020-02-01')));
    }

    public function test_strategy_gets_in_range_history_and_only_the_latest_pre_range_value_as_of_simulation_time(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $pair = $this->pair($type, 'EUR', 'USD');
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $fundamental->marketPairs()->attach($pair->id);

        $this->content($fundamental, 'CPI', '2024-06-01', '2024-07-15 08:30:00', 0.8);
        $this->content($fundamental, 'CPI', '2024-07-01', '2024-08-15 08:30:00', 0.9);
        $this->content($fundamental, 'CPI', '2024-08-01', '2024-09-15 08:30:00', 1.0);
        $this->content($fundamental, 'CPI', '2024-09-01', '2024-10-10 08:30:00', 1.1);
        $this->content($fundamental, 'CPI', '2024-10-01', '2024-10-25 08:30:00', 1.2);
        $this->content($fundamental, 'CPI', '2024-11-01', '2024-11-15 08:30:00', 1.3);
        $this->content($fundamental, 'GDP', '2024-07-01', '2024-09-20 12:00:00', ['growth' => 2.4]);
        $this->content($fundamental, 'GDP', '2024-10-01', '2024-11-20 12:00:00', ['growth' => 2.7]);

        $data = app(LoadFundamentalDataForBacktestAction::class)->execute(
            $pair->id,
            new DateTimeImmutable('2024-10-01 00:00:00'),
            new DateTimeImmutable('2024-10-31 23:59:59'),
        );
        $entity = $data->get($fundamental->id);

        $this->assertNotNull($entity);
        $cpiAvailableOnOctober20 = $entity->observations('CPI', new DateTimeImmutable('2024-10-20 23:59:59'));
        $this->assertCount(2, $cpiAvailableOnOctober20);
        $this->assertSame([1.0, 1.1], array_map(
            static fn ($observation): float => (float) $observation->value,
            $cpiAvailableOnOctober20,
        ));
        $this->assertSame(
            '2024-09-01',
            $entity->latestAvailable('CPI', new DateTimeImmutable('2024-10-20 23:59:59'))
                ?->datetime->format('Y-m-d'),
        );
        $this->assertSame(
            ['growth' => 2.4],
            $entity->latestAvailable('GDP', new DateTimeImmutable('2024-10-20 23:59:59'))?->value,
        );

        $this->assertSame(
            1.2,
            $entity->latestAvailable('CPI', new DateTimeImmutable('2024-10-25 08:30:00'))?->value,
        );
        $this->assertCount(3, $entity->observations('CPI', new DateTimeImmutable('2024-10-31 23:59:59')));
        $this->assertCount(1, $entity->observations('GDP', new DateTimeImmutable('2024-10-31 23:59:59')));
    }

    public function test_external_fundamental_import_session_accepts_scalar_and_object_observations(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $pair = $this->pair($type, 'EUR', 'USD');
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $fundamental->marketPairs()->attach($pair->id);

        $token = $this->startImportSession($fundamental);
        $session = FundamentalImportSession::query()->sole();

        $this->assertIsString($token);
        $this->assertNotSame($token, $session->token_hash);
        $this->assertSame(hash('sha256', $token), $session->token_hash);
        $this->get('/workbench/data-import')
            ->assertOk()
            ->assertSee($token)
            ->assertSee('/api/data-import/fundamentals/observations');
        $this->get("/workbench/fundamentals/{$fundamental->id}")
            ->assertOk()
            ->assertDontSee($token)
            ->assertDontSee('Import observations via API')
            ->assertDontSee('/api/data-import/fundamentals/observations');

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [
                [
                    'code' => 'cpi',
                    'name' => 'Consumer Price Index',
                    'datetime' => '2024-09-01 00:00:00',
                    'released_at' => '2024-10-10 08:30:00',
                    'value' => 1.1,
                ],
                [
                    'code' => 'GDP',
                    'name' => 'GDP Growth',
                    'datetime' => '2024-07-01 00:00:00',
                    'released_at' => null,
                    'value' => ['growth' => 2.4, 'unit' => 'percent'],
                ],
                [
                    'code' => 'UNEMPLOYMENT',
                    'name' => 'Unemployment',
                    'datetime' => '2024-09-01 00:00:00',
                    'value' => null,
                ],
            ],
        ])->assertOk()->assertExactJson([
            'imported' => 3,
            'inserted' => 3,
            'updated' => 0,
        ]);

        $this->assertDatabaseCount('fundamental_contents', 3);
        $this->assertDatabaseHas('fundamental_contents', [
            'fundamental_id' => $fundamental->id,
            'code' => 'CPI',
            'datetime' => '2024-09-01 00:00:00',
            'released_at' => '2024-10-10 08:30:00',
            'value' => '1.1',
        ]);
        $this->assertDatabaseHas('fundamental_contents', [
            'fundamental_id' => $fundamental->id,
            'code' => 'GDP',
            'released_at' => null,
            'value' => '{"growth":2.4,"unit":"percent"}',
        ]);
        $this->assertDatabaseHas('fundamental_contents', [
            'fundamental_id' => $fundamental->id,
            'code' => 'UNEMPLOYMENT',
            'released_at' => null,
            'value' => 'null',
        ]);

        $strategyData = app(LoadFundamentalDataForBacktestAction::class)->execute(
            (int) $pair->id,
            new DateTimeImmutable('2024-10-01'),
            new DateTimeImmutable('2024-10-31'),
        );
        $this->assertSame(
            1.1,
            $strategyData->get($fundamental->id)
                ?->latestAvailable('CPI', new DateTimeImmutable('2024-10-20'))?->value,
        );
        $this->assertNull(
            $strategyData->get($fundamental->id)
                ?->latestAvailable('GDP', new DateTimeImmutable('2024-10-20')),
        );
    }

    public function test_external_fundamental_import_is_idempotent_and_updates_the_same_indicator_period(): void
    {
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $token = $this->startImportSession($fundamental);
        $observation = [
            'code' => 'CPI',
            'name' => 'Consumer Price Index',
            'datetime' => '2024-09-01 00:00:00',
            'released_at' => '2024-10-10 08:30:00',
            'value' => 1.1,
        ];

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [$observation],
        ])->assertOk()->assertExactJson(['imported' => 1, 'inserted' => 1, 'updated' => 0]);

        $observation['name'] = 'CPI revised by source';
        $observation['released_at'] = '2024-10-15 08:30:00';
        $observation['value'] = 1.2;
        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [$observation],
        ])->assertOk()->assertExactJson(['imported' => 1, 'inserted' => 0, 'updated' => 1]);

        $this->assertDatabaseCount('fundamental_contents', 1);
        $content = FundamentalContent::query()->sole();
        $this->assertSame('CPI revised by source', $content->name);
        $this->assertSame(1.2, $content->value);
        $this->assertSame('2024-10-15 08:30:00', $content->released_at->format('Y-m-d H:i:s'));
    }

    public function test_external_fundamental_import_rejects_bad_token_and_stopped_sessions(): void
    {
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $payload = ['observations' => [$this->apiObservation()]];

        $this->postJson('/api/data-import/fundamentals/observations', $payload)->assertUnauthorized();
        $this->withToken('invalid')->postJson('/api/data-import/fundamentals/observations', $payload)->assertUnauthorized();

        $token = $this->startImportSession($fundamental);
        $session = FundamentalImportSession::query()->sole();
        $this->post("/workbench/data-import/fundamental-sessions/{$fundamental->id}/{$session->id}/stop")
            ->assertRedirect('/workbench/data-import');
        $this->assertDatabaseHas('fundamental_import_sessions', [
            'id' => $session->id,
            'status' => 'stopped',
            'active_fundamental_id' => null,
        ]);

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('fundamental_contents', 0);
    }

    public function test_external_fundamental_import_rejects_duplicate_batch_keys_and_invalid_observations(): void
    {
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $token = $this->startImportSession($fundamental);
        $observation = $this->apiObservation();

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [$observation, $observation],
        ])->assertUnprocessable()->assertJsonValidationErrors('observations.1.datetime');

        $invalid = $this->apiObservation();
        $invalid['code'] = 'CPI-USD';
        $invalid['datetime'] = '2024-13-40 00:00:00';
        unset($invalid['value']);
        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [$invalid],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'observations.0.code',
                'observations.0.datetime',
                'observations.0.value',
            ]);

        $this->withToken($token)->postJson('/api/data-import/fundamentals/observations', [
            'observations' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('observations');
        $this->assertDatabaseCount('fundamental_contents', 0);
    }

    public function test_fundamental_entity_allows_only_one_active_import_session_until_stopped(): void
    {
        $fundamental = Fundamental::create(['name' => 'United States Economy']);
        $this->startImportSession($fundamental);

        $this->from('/workbench/data-import')
            ->post('/workbench/data-import/fundamental-sessions', ['fundamental_id' => $fundamental->id])
            ->assertRedirect('/workbench/data-import')
            ->assertSessionHasErrors('fundamental');

        $session = FundamentalImportSession::query()->sole();
        $this->post("/workbench/data-import/fundamental-sessions/{$fundamental->id}/{$session->id}/stop")->assertRedirect();
        $this->startImportSession($fundamental);
        $this->assertDatabaseCount('fundamental_import_sessions', 2);
    }

    private function startImportSession(Fundamental $fundamental): string
    {
        $this->post('/workbench/data-import/fundamental-sessions', [
            'fundamental_id' => $fundamental->id,
        ])->assertRedirect('/workbench/data-import')->assertSessionHas('fundamental_import_token');

        return (string) session('fundamental_import_token');
    }

    private function pair(MarketType $type, string $base, string $quote): MarketPair
    {
        return MarketPair::create([
            'market_type_id' => $type->id,
            'base' => $base,
            'quote' => $quote,
            'quantity_unit' => 'units',
            'contract_size' => '1.00000000',
        ]);
    }

    private function content(
        Fundamental $fundamental,
        string $code,
        string $datetime,
        ?string $releasedAt,
        mixed $value,
    ): FundamentalContent {
        return FundamentalContent::create([
            'fundamental_id' => $fundamental->id,
            'code' => $code,
            'name' => $code,
            'value' => $value,
            'datetime' => $datetime,
            'released_at' => $releasedAt,
        ]);
    }

    private function price(MarketPair $pair, TimeframeType $timeframe, string $timestamp): void
    {
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => $timeframe->id,
            'open' => '1.100000',
            'high' => '1.100000',
            'low' => '1.100000',
            'close' => '1.100000',
        ]);
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
