<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\CashFlows\Model\MarketPairCashFlow;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Timeframes\Model\TimeframeType;
use App\Platform\Strategy\Strategy;
use DateTimeImmutable;
use Exact\Collection\Vec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Nikaido\Strategy\Enums\StrategySignal;
use Nikaido\Strategy\Models\StrategyDecision;
use Nikaido\Strategy\Models\StrategyExecutionContext;
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

    public function test_it_loads_and_applies_cash_flows_only_when_enabled_for_the_backtest(): void
    {
        $timeframe = TimeframeType::create(['name' => '1h']);
        $marketType = MarketType::create(['name' => 'Equities']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'AAPL',
            'quote' => 'USD',
        ]);
        foreach (['2026-03-01 00:00:00', '2026-03-02 00:00:00', '2026-03-03 00:00:00'] as $timestamp) {
            $this->createCandle($pair, $timestamp, $timeframe->id);
        }
        MarketPairCashFlow::query()->create([
            'market_pair_id' => $pair->id,
            'event_type' => 'dividend',
            'amount_per_unit' => '0.500000000000',
            'announced_at' => '2026-03-01 00:00:00',
            'available_at' => '2026-03-01 00:00:00',
            'ex_date' => '2026-03-02 00:00:00',
            'status' => 'confirmed',
        ]);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')
            ->once()
            ->withArgs(function (iterable $candles, StrategyExecutionContext $context): bool {
                $this->assertCount(1, $context->cashFlows()->availableAt(new DateTimeImmutable('2026-03-02 00:00:00')));

                return true;
            })
            ->andReturn(Vec::fromArray([
                new StrategyDecision(StrategySignal::Long, 0.9, 1.3, 10),
                new StrategyDecision(StrategySignal::None, null, null),
                new StrategyDecision(StrategySignal::Close, null, null),
            ]));
        $this->app->instance(Strategy::class, $strategy);

        $result = app(RunSpecificPairBacktestAction::class)->execute(new SpecificPairTestConfig(
            base: 'AAPL',
            quote: 'USD',
            from: new DateTimeImmutable('2026-03-01 00:00:00'),
            to: new DateTimeImmutable('2026-03-03 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: $timeframe->id,
            initialCapital: 10000,
            marketPairId: $pair->id,
            includeCashFlows: true,
        ));

        $this->assertCount(1, $result->cashFlows);
        $this->assertSame(5.0, $result->cashFlows->first()['amount']);
        $this->assertSame(
            $result->initialCapital + $result->orders->first()->realizedProfitLoss + 5.0,
            $result->endingCapital,
        );
    }

    public function test_it_rejects_a_backtest_with_no_candles_in_the_selected_range(): void
    {
        $timeframe = TimeframeType::create(['name' => '1h']);
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $this->createCandle($pair, '2026-10-09 10:00:00');

        try {
            app(RunSpecificPairBacktestAction::class)->execute(new SpecificPairTestConfig(
                base: 'EUR',
                quote: 'USD',
                from: new DateTimeImmutable('2026-10-10 00:00:00'),
                to: new DateTimeImmutable('2026-10-10 23:59:59'),
                activeFrom: false,
                activeUntil: false,
                timeframeTypeId: $timeframe->id,
                marketPairId: $pair->id,
            ));
            $this->fail('A backtest with no in-range candles must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'No market price candles were found for this pair, timeframe, and date range.',
                $exception->errors()['from'][0],
            );
        }
    }

    public function test_it_rejects_a_backtest_when_all_candles_are_outside_the_active_schedule(): void
    {
        $timeframe = TimeframeType::create(['name' => '1h']);
        $marketType = MarketType::create(['name' => 'Forex']);
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => 'EUR',
            'quote' => 'USD',
        ]);
        $this->createCandle($pair, '2026-10-09 08:00:00');

        try {
            app(RunSpecificPairBacktestAction::class)->execute(new SpecificPairTestConfig(
                base: 'EUR',
                quote: 'USD',
                from: new DateTimeImmutable('2026-10-09 00:00:00'),
                to: new DateTimeImmutable('2026-10-09 23:59:59'),
                activeFrom: '09:00',
                activeUntil: '17:00',
                timeframeTypeId: $timeframe->id,
                marketPairId: $pair->id,
            ));
            $this->fail('A backtest with no active candles must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'No candles fall within the selected active hours and days.',
                $exception->errors()['from'][0],
            );
        }
    }

    private function createCandle(MarketPair $pair, string $timestamp, int $timeframeTypeId = 1): MarketPrice
    {
        return MarketPrice::create([
            'pair_id' => $pair->id,
            'price_date' => $timestamp,
            'timeframe_type_id' => $timeframeTypeId,
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
