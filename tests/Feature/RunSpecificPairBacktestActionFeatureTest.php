<?php

namespace Tests\Feature;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunSpecificPairBacktestActionFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queries_the_configured_pair_and_runs_only_during_active_hours(): void
    {
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);

        $passiveCandle = $this->createCandle($pair, '2026-10-09 08:59:00');
        $activeCandle = $this->createCandle($pair, '2026-10-09 09:00:00');
        $this->createCandle($pair, '2026-10-09 17:01:00');

        $results = app(RunSpecificPairBacktestAction::class)->execute(
            new SpecificPairTestConfig(
                'eur',
                'usd',
                new DateTimeImmutable('2026-10-09 00:00:00'),
                new DateTimeImmutable('2026-10-09 23:59:59'),
                '09:00',
                '17:00',
            ),
        );

        $this->assertCount(1, $results->decisions);
        $this->assertSame($activeCandle->id, $results->decisions->first()->candle->id);
        $this->assertSame('2026-10-09 08:59:00', $passiveCandle->fresh()->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results->decisions->first()->entered);
        $this->assertTrue($results->orders->isEmpty());
    }

    public function test_it_uses_the_exact_pair_id_when_symbols_repeat_across_market_types(): void
    {
        $timeframe = TimeframeType::create(['name' => '1h']);
        $forex = MarketType::create(['name' => 'Forex']);
        $crypto = MarketType::create(['name' => 'Crypto']);
        $firstPair = MarketPair::create([
            'market_type_id' => $forex->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $selectedPair = MarketPair::create([
            'market_type_id' => $crypto->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $this->createCandle($firstPair, '2026-10-09 09:00:00');
        $selectedCandle = $this->createCandle($selectedPair, '2026-10-09 10:00:00');

        $results = app(RunSpecificPairBacktestAction::class)->execute(new SpecificPairTestConfig(
            base: 'EUR',
            quote: 'USD',
            from: new DateTimeImmutable('2026-10-09 00:00:00'),
            to: new DateTimeImmutable('2026-10-09 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: $timeframe->id,
            marketPairId: $selectedPair->id,
        ));

        $this->assertCount(1, $results->decisions);
        $this->assertSame($selectedCandle->id, $results->decisions->first()->candle->id);
    }

    private function createCandle(MarketPair $pair, string $timestamp): MarketPrice
    {
        return MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            'bid_open' => '1.199000',
            'bid_high' => '1.203000',
            'bid_low' => '1.193000',
            'bid_close' => '1.199000',
            'ask_open' => '1.201000',
            'ask_high' => '1.207000',
            'ask_low' => '1.197000',
            'ask_close' => '1.201000',
            'high' => '1.205000',
            'low' => '1.195000',
        ]);
    }
}
