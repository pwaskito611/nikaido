<?php

namespace Tests\Feature;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\MarketType;
use App\Strategy\Config\SpecificPairTestConfig;
use App\Strategy\Strategy;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

        $strategy = new class extends Strategy
        {
            /** @var list<MarketPrice> */
            public array $calledWith = [];

            /**
             * @param  Collection<int, MarketPrice>  $history
             */
            public function execute(MarketPrice $candle, Collection $history): bool
            {
                $this->calledWith[] = $candle;

                return false;
            }
        };

        $results = app(RunSpecificPairBacktestAction::class)->execute(
            new SpecificPairTestConfig(
                'eur',
                'usd',
                new DateTimeImmutable('2026-10-09 00:00:00'),
                new DateTimeImmutable('2026-10-09 23:59:59'),
                '09:00',
                '17:00',
            ),
            $strategy,
        );

        $this->assertSame(1, $results->count());
        $this->assertSame($activeCandle->id, $results->at(0)['candle']->id);
        $this->assertSame([$activeCandle->id], array_map(
            fn (MarketPrice $candle): int => $candle->id,
            $strategy->calledWith,
        ));
        $this->assertSame('2026-10-09 08:59:00', $passiveCandle->fresh()->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results->at(0)['result']);
    }

    private function createCandle(MarketPair $pair, string $timestamp): MarketPrice
    {
        return MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
        ]);
    }
}
