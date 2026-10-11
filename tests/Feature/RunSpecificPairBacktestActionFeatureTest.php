<?php

namespace Tests\Feature;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\MarketType;
use App\Strategy\Config\SpecificPairTestConfig;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

        Http::fake([
            '*/api/strategy/' => Http::response([
                'signal' => 'none',
                'stop_loss' => null,
                'take_profit' => null,
            ], 200),
        ]);

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

        $this->assertSame(1, $results['results']->count());
        $this->assertSame($activeCandle->id, $results['results']->at(0)['candle']->id);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/strategy/')
            && $request->data() === []);
        $this->assertSame('2026-10-09 08:59:00', $passiveCandle->fresh()->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results['results']->at(0)['result']);
        $this->assertTrue($results['order_history']->isEmpty());
    }

    private function createCandle(MarketPair $pair, string $timestamp): MarketPrice
    {
        return MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => 1,
            'open' => '1.100000',
            'close' => '1.200000',
            'bid' => '1.199000',
            'ask' => '1.201000',
            'high' => '1.205000',
            'low' => '1.195000',
        ]);
    }
}
