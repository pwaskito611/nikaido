<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\CashFlows\Action\LoadMarketPairCashFlowsForBacktestAction;
use App\Feature\Fundamentals\Action\LoadFundamentalDataForBacktestAction;
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

class RelatedMarketPairsTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_pair_relationships_can_be_created_edited_and_removed_directionally(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1d']);
        $firstRelated = $this->pair($type, 'GBP', 'USD');
        $secondRelated = $this->pair($type, 'USD', 'JPY');

        $this->post('/workbench/market-pairs', [
            'market_type_id' => $type->id,
            'base' => 'AUD',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
            'related_market_pairs_present' => '1',
            'related_market_pair_ids' => [$firstRelated->id, $secondRelated->id],
            'related_timeframes' => [
                $firstRelated->id => $timeframe->id,
                $secondRelated->id => '',
            ],
        ])->assertRedirect();
        $primary = MarketPair::query()->where('base', 'AUD')->firstOrFail();

        $this->assertDatabaseCount('market_pair_related_pairs', 2);
        $this->assertTrue($primary->fresh()->relatedPairs->contains('id', $firstRelated->id));
        $this->assertTrue($primary->fresh()->relatedPairs->contains('id', $secondRelated->id));
        $this->assertFalse($firstRelated->fresh()->relatedPairs->contains('id', $primary->id));

        $this->put("/workbench/market-pairs/{$primary->id}", [
            'market_type_id' => $type->id,
            'base' => 'AUD',
            'quote' => 'NZD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ])->assertRedirect("/workbench/market-pairs/{$primary->id}");
        $this->assertDatabaseCount('market_pair_related_pairs', 2);

        $this->get("/workbench/market-pairs/{$primary->id}/edit")
            ->assertOk()
            ->assertSee('Related Market Pairs')
            ->assertSee('related-market-pair-search')
            ->assertSee('related-pair-list')
            ->assertSee('Search by pair or market type...')
            ->assertSee('selected', false);

        $this->put("/workbench/market-pairs/{$primary->id}", [
            'market_type_id' => $type->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
            'related_market_pairs_present' => '1',
            'related_market_pair_ids' => [$secondRelated->id],
            'related_timeframes' => [$secondRelated->id => $timeframe->id],
        ])->assertRedirect("/workbench/market-pairs/{$primary->id}");

        $this->assertDatabaseCount('market_pair_related_pairs', 1);
        $this->assertDatabaseHas('market_pair_related_pairs', [
            'market_pair_id' => $primary->id,
            'related_market_pair_id' => $secondRelated->id,
            'timeframe_type_id' => $timeframe->id,
        ]);
        $this->assertDatabaseMissing('market_pair_related_pairs', [
            'market_pair_id' => $primary->id,
            'related_market_pair_id' => $firstRelated->id,
        ]);
    }

    public function test_market_pair_request_rejects_self_and_duplicate_related_pairs(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $primary = $this->pair($type, 'EUR', 'USD');
        $related = $this->pair($type, 'GBP', 'USD');
        $timeframe = TimeframeType::create(['name' => '1d']);
        $payload = [
            'market_type_id' => $type->id,
            'base' => 'EUR',
            'quote' => 'USD',
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ];

        $this->from("/workbench/market-pairs/{$primary->id}/edit")
            ->put("/workbench/market-pairs/{$primary->id}", [
                ...$payload,
                'related_market_pairs_present' => '1',
                'related_market_pair_ids' => [$primary->id],
            ])
            ->assertRedirect("/workbench/market-pairs/{$primary->id}/edit")
            ->assertSessionHasErrors('related_market_pair_ids.0');

        $this->from("/workbench/market-pairs/{$primary->id}/edit")
            ->put("/workbench/market-pairs/{$primary->id}", [
                ...$payload,
                'related_market_pairs_present' => '1',
                'related_market_pair_ids' => [$related->id, $related->id],
            ])
            ->assertRedirect("/workbench/market-pairs/{$primary->id}/edit")
            ->assertSessionHasErrors('related_market_pair_ids.1');

        $this->from("/workbench/market-pairs/{$primary->id}/edit")
            ->put("/workbench/market-pairs/{$primary->id}", [
                ...$payload,
                'related_market_pairs_present' => '1',
                'related_market_pair_ids' => [99999],
            ])
            ->assertRedirect("/workbench/market-pairs/{$primary->id}/edit")
            ->assertSessionHasErrors('related_market_pair_ids.0');

        $this->from("/workbench/market-pairs/{$primary->id}/edit")
            ->put("/workbench/market-pairs/{$primary->id}", [
                ...$payload,
                'related_market_pairs_present' => '1',
                'related_market_pair_ids' => [$related->id],
                'related_timeframes' => [$related->id => 99999],
            ])
            ->assertRedirect("/workbench/market-pairs/{$primary->id}/edit")
            ->assertSessionHasErrors("related_timeframes.{$related->id}");

        $this->put("/workbench/market-pairs/{$primary->id}", [
            ...$payload,
            'related_market_pairs_present' => '1',
            'related_market_pair_ids' => [$primary->id],
            'related_timeframes' => [$primary->id => $timeframe->id],
        ])->assertRedirect("/workbench/market-pairs/{$primary->id}");

        $this->assertDatabaseHas('market_pair_related_pairs', [
            'market_pair_id' => $primary->id,
            'related_market_pair_id' => $primary->id,
            'timeframe_type_id' => $timeframe->id,
        ]);
    }

    public function test_pivot_database_constraints_reject_self_and_duplicate_relationships(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1d']);
        $primary = $this->pair($type, 'EUR', 'USD');
        $related = $this->pair($type, 'GBP', 'USD');
        $primary->relatedPairs()->attach($related->id);

        try {
            DB::table('market_pair_related_pairs')->insert([
                'market_pair_id' => $primary->id,
                'related_market_pair_id' => $related->id,
            ]);
            $this->fail('The unique pivot constraint must reject a duplicate relation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        try {
            DB::table('market_pair_related_pairs')->insert([
                'market_pair_id' => $primary->id,
                'related_market_pair_id' => $primary->id,
            ]);
            $this->fail('The pivot self-reference guard must reject a self relation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $primary->relatedPairs()->attach($primary->id, ['timeframe_type_id' => $timeframe->id]);
        $this->assertDatabaseHas('market_pair_related_pairs', [
            'market_pair_id' => $primary->id,
            'related_market_pair_id' => $primary->id,
            'timeframe_type_id' => $timeframe->id,
        ]);
    }

    public function test_backtest_context_exposes_related_prices_at_their_actual_timestamps_only(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $dailyTimeframe = TimeframeType::create(['name' => '1d']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);
        $primary = $this->pair($type, 'EUR', 'GBP');
        $related = $this->pair($type, 'GBP', 'USD');
        $emptyRelated = $this->pair($type, 'USD', 'JPY');
        $primary->relatedPairs()->sync([
            $primary->id => ['timeframe_type_id' => $dailyTimeframe->id],
            $related->id => ['timeframe_type_id' => $dailyTimeframe->id],
            $emptyRelated->id => ['timeframe_type_id' => null],
        ]);

        $this->price($primary, $timeframe, '2020-01-02 10:00:00', '1.10');
        $this->price($primary, $timeframe, '2020-01-03 10:00:00', '1.11');
        $this->price($primary, $timeframe, '2020-01-04 10:00:00', '1.12');
        $this->price($primary, $dailyTimeframe, '2020-01-01 10:00:00', '1.19');
        $this->price($primary, $dailyTimeframe, '2020-01-02 10:00:00', '1.20');
        $this->price($primary, $dailyTimeframe, '2020-01-03 10:00:00', '1.21');
        $this->price($related, $dailyTimeframe, '2020-01-01 10:00:00', '1.20');
        $this->price($related, $dailyTimeframe, '2020-01-02 09:30:00', '1.21');
        $this->price($related, $dailyTimeframe, '2020-01-02 10:30:00', '1.22');
        $this->price($related, $dailyTimeframe, '2020-01-05 10:00:00', '1.23');
        $this->price($related, $otherTimeframe, '2020-01-02 10:00:00', '1.24');

        $strategy = new class extends Strategy
        {
            public ?StrategyExecutionContext $context = null;

            public int $calls = 0;

            public int $primaryCandleCount = 0;

            public function execute(iterable $candles, StrategyExecutionContext $context): iterable
            {
                $this->calls++;
                $this->context = $context;
                $decisions = [];
                foreach ($candles as $index => $_candle) {
                    $this->primaryCandleCount++;
                    $decisions[$index] = new StrategyDecision(StrategySignal::None, null, null);
                }

                return $decisions;
            }
        };
        $config = new SpecificPairTestConfig(
            base: $primary->base,
            quote: $primary->quote,
            from: new DateTimeImmutable('2020-01-02 00:00:00'),
            to: new DateTimeImmutable('2020-01-03 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: $timeframe->id,
            marketPairId: $primary->id,
        );

        $result = (new RunSpecificPairBacktestAction(
            app(MarketPairRepository::class),
            new SpecificPairBacktestProcess($strategy),
            app(LoadFundamentalDataForBacktestAction::class),
            app(LoadMarketPairCashFlowsForBacktestAction::class),
        ))->execute($config);

        $this->assertCount(0, $result->orders);
        $this->assertSame(1, $strategy->calls);
        $this->assertSame(2, $strategy->primaryCandleCount);
        $this->assertSame($primary->id, $strategy->context?->primaryMarketPairId);
        $this->assertSame($timeframe->id, $strategy->context?->primaryTimeframeTypeId);
        $this->assertSame('EUR/GBP', $strategy->context?->primaryMarketPairSymbol);
        $this->assertArrayHasKey($primary->id, $strategy->context?->relatedMarketPairs);
        $this->assertArrayHasKey($related->id, $strategy->context?->relatedMarketPairs);
        $this->assertArrayHasKey($emptyRelated->id, $strategy->context?->relatedMarketPairs);

        $primaryDailySeries = $strategy->context->relatedMarketPairs[$primary->id];
        $this->assertSame($dailyTimeframe->id, $primaryDailySeries->timeframeTypeId);
        $this->assertSame('1.200000', $primaryDailySeries->candleAt('2020-01-02 10:00:00')->close);
        $relatedSeries = $strategy->context->relatedMarketPairs[$related->id];
        $this->assertSame('GBP', $relatedSeries->base);
        $this->assertSame('USD', $relatedSeries->quote);
        $this->assertSame($dailyTimeframe->id, $relatedSeries->timeframeTypeId);
        $this->assertSame('2020-01-01 10:00:00', $relatedSeries->candleAt('2020-01-01 10:00:00')->price_date->format('Y-m-d H:i:s'));
        $this->assertSame('2020-01-02 09:30:00', $relatedSeries->candleAt('2020-01-02 09:30:00')->price_date->format('Y-m-d H:i:s'));
        $this->assertSame('2020-01-02 10:30:00', $relatedSeries->candleAt('2020-01-02 10:30:00')->price_date->format('Y-m-d H:i:s'));
        $this->assertNull($relatedSeries->candleAt('2020-01-04 10:00:00'));
        $this->assertNull($relatedSeries->candleAt('2020-01-05 10:00:00'));
        $this->assertSame($timeframe->id, $strategy->context->relatedMarketPairs[$emptyRelated->id]->timeframeTypeId);
        $this->assertCount(0, $strategy->context->relatedMarketPairs[$emptyRelated->id]->candlesByTimestamp);
    }

    private function pair(MarketType $type, string $base, string $quote): MarketPair
    {
        return MarketPair::create([
            'market_type_id' => $type->id,
            'base' => $base,
            'quote' => $quote,
            'quantity_unit' => 'units',
            ...$this->cfdProfile(),
        ]);
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

    private function price(MarketPair $pair, TimeframeType $timeframe, string $timestamp, string $price): void
    {
        MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => $timeframe->id,
            'open' => $price,
            'close' => $price,
            'high' => $price,
            'low' => $price,
        ]);
    }
}
