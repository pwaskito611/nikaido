<?php

namespace Tests\Unit\Engine\Actions;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Config\SpecificPairTestConfig;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Strategy\Enums\StrategySignal;
use App\Strategy\Models\StrategyDecision;
use App\Strategy\Strategy;
use DateTimeImmutable;
use DateTimeInterface;
use Exact\Collection\Vec;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class RunSpecificPairBacktestActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_skips_inactive_candles_and_returns_only_active_decisions(): void
    {
        $candles = new EloquentCollection([
            $this->candle('2026-10-09 08:59:00'),
            $this->candle('2026-10-09 09:00:00'),
            $this->candle('2026-10-09 17:01:00'),
            $this->candle('2026-10-10 10:00:00'),
            $this->candle('2026-10-12 10:00:00'),
        ]);

        $pair = new MarketPair;
        $pair->setRelation('prices', $candles);

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')
            ->once()
            ->with(
                'EUR',
                'USD',
                Mockery::on(
                    fn (DateTimeInterface $date): bool => $date == new DateTimeImmutable('2026-10-09 00:00:00')
                ),
                Mockery::on(
                    fn (DateTimeInterface $date): bool => $date == new DateTimeImmutable('2026-10-12 23:59:59')
                ),
                null,
            )
            ->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-12 23:59:59'),
            '09:00',
            '17:00',
            [6, 7],
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);

        $this->assertSame(2, $results['results']->count());
        $this->assertSame('2026-10-09 09:00:00', $results['results']->at(0)['candle']->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results['results']->at(0)['result']);
        $this->assertTrue($results['order_history']->isEmpty());
        $this->assertSame('2026-10-12 10:00:00', $results['results']->at(1)['candle']->price_date->format('Y-m-d H:i:s'));
    }

    public function test_false_active_hours_allow_full_day_except_configured_days(): void
    {
        $candles = new EloquentCollection([
            $this->candle('2026-10-09 02:00:00'),
            $this->candle('2026-10-10 02:00:00'),
        ]);
        $pair = new MarketPair;
        $pair->setRelation('prices', $candles);

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            [6],
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);

        $this->assertSame(1, $results['results']->count());
        $this->assertSame('2026-10-09 02:00:00', $results['results']->at(0)['candle']->price_date->format('Y-m-d H:i:s'));
    }

    public function test_it_tracks_the_price_closest_to_stop_loss_and_closes_orders_outside_active_hours(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', [
                'ask_open' => 1.101,
                'bid_low' => 1.100,
                'bid_high' => 1.110,
            ]),
            $this->candle('2026-10-09 17:30:00', [
                'bid_close' => 1.096,
                'bid_low' => 1.095,
                'bid_high' => 1.105,
            ]),
            $this->candle('2026-10-09 18:00:00', [
                'bid_close' => 1.0905,
                'bid_low' => 1.089,
                'bid_high' => 1.100,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);
        $order = $results['order_history']->at(0);

        $this->assertSame(1, $results['results']->count());
        $this->assertSame('2026-10-09 09:00:00', $order->openDate);
        $this->assertSame('2026-10-09 18:00:00', $order->closeDate);
        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.101, $order->entryPrice);
        $this->assertSame(1.0905, $order->closePrice);
        $this->assertSame([
            'price' => 1.0905,
            'date' => '2026-10-09 18:00:00',
            'source' => 'bid_close',
            'distance' => 0.0005,
        ], $order->closestToStopLoss);
    }

    public function test_it_uses_generic_ohlc_and_close_when_bid_quotes_are_unavailable(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['ask_open' => 1.101]),
            $this->candle('2026-10-09 10:00:00', [
                'low' => 1.089,
                'close' => 1.088,
                'bid_low' => null,
                'bid_high' => null,
                'bid_close' => null,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);
        $order = $results['order_history']->at(0);

        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.088, $order->closePrice);
        $this->assertSame('low', $order->closestToStopLoss['source']);
    }

    public function test_it_uses_generic_ohlc_and_close_when_ask_quotes_are_unavailable(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid_open' => 1.099]),
            $this->candle('2026-10-09 10:00:00', [
                'high' => 1.111,
                'close' => 1.112,
                'ask_high' => null,
                'ask_low' => null,
                'ask_close' => null,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 1.11, 1.08),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);
        $order = $results['order_history']->at(0);

        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.112, $order->closePrice);
        $this->assertSame('high', $order->closestToStopLoss['source']);
    }

    public function test_it_closes_short_orders_at_take_profit_and_honors_close_signals(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid_open' => 1.099]),
            $this->candle('2026-10-09 10:00:00', [
                'ask_close' => 1.0805,
                'ask_high' => 1.095,
                'ask_low' => 1.079,
            ]),
            $this->candle('2026-10-09 11:00:00', ['ask_open' => 1.101]),
            $this->candle('2026-10-09 12:00:00', [
                'bid_close' => 1.105,
                'bid_low' => 1.100,
                'bid_high' => 1.110,
                'ask_close' => 1.105,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 1.11, 1.08),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategy))->execute($config);

        $this->assertSame(2, $results['order_history']->count());
        $firstOrder = $results['order_history']->at(0);
        $secondOrder = $results['order_history']->at(1);
        $this->assertSame('take_profit', $firstOrder->closeReason);
        $this->assertSame('2026-10-09 10:00:00', $firstOrder->closeDate);
        $this->assertSame([
            'price' => 1.0805,
            'date' => '2026-10-09 10:00:00',
            'source' => 'ask_close',
            'distance' => 0.0005,
        ], $firstOrder->closestToTakeProfit);
        $this->assertSame(100.0, $firstOrder->riskAmount);
        $this->assertEqualsWithDelta(100 / 0.011, $firstOrder->positionSize, 0.000001);
        $this->assertEqualsWithDelta(172.7272727, $firstOrder->profitLoss, 0.000001);
        $this->assertEqualsWithDelta(10172.7272727, $firstOrder->capitalAfterClose, 0.000001);
        $this->assertSame('close', $secondOrder->closeReason);
        $this->assertSame('2026-10-09 12:00:00', $secondOrder->closeDate);
        $this->assertSame(1.105, $secondOrder->closePrice);
        $this->assertEqualsWithDelta($firstOrder->capitalAfterClose * 0.01, $secondOrder->riskAmount, 0.000001);
        $this->assertEqualsWithDelta(
            $firstOrder->capitalAfterClose + $secondOrder->profitLoss,
            $results['ending_capital'],
            0.000001,
        );
    }

    /**
     * @param  array<string, int|float|string|null>  $attributes
     */
    private function candle(string $timestamp, array $attributes = []): MarketPrice
    {
        $open = (float) ($attributes['open'] ?? 1.1);
        $close = (float) ($attributes['close'] ?? $open);
        $high = (float) ($attributes['high'] ?? $open);
        $low = (float) ($attributes['low'] ?? $open);

        return new MarketPrice([
            'price_date' => $timestamp,
            'open' => $open,
            'close' => $close,
            'high' => $high,
            'low' => $low,
            'bid_open' => $open,
            'bid_high' => $high,
            'bid_low' => $low,
            'bid_close' => $close,
            'ask_open' => $open,
            'ask_high' => $high,
            'ask_low' => $low,
            'ask_close' => $close,
            ...$attributes,
        ]);
    }
}
