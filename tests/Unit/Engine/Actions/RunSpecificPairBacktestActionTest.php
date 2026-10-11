<?php

namespace Tests\Unit\Engine\Actions;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Enums\StrategySignal;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Models\StrategyDecision;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Services\StrategyClient;
use App\Strategy\Config\SpecificPairTestConfig;
use DateTimeImmutable;
use DateTimeInterface;
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

    public function test_it_skips_inactive_candles_and_calls_strategy_only_for_active_candles(): void
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
            )
            ->andReturn($pair);

        $strategyClient = Mockery::mock(StrategyClient::class);
        $strategyClient->shouldReceive('execute')->twice()->andReturn(
            new StrategyDecision(StrategySignal::None, null, null),
        );
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-12 23:59:59'),
            '09:00',
            '17:00',
            [6, 7],
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategyClient))->execute($config);

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

        $strategyClient = Mockery::mock(StrategyClient::class);
        $strategyClient->shouldReceive('execute')->once()->andReturn(
            new StrategyDecision(StrategySignal::None, null, null),
        );
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            [6],
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategyClient))->execute($config);

        $this->assertSame(1, $results['results']->count());
        $this->assertSame('2026-10-09 02:00:00', $results['results']->at(0)['candle']->price_date->format('Y-m-d H:i:s'));
    }

    public function test_it_tracks_the_price_closest_to_stop_loss_and_closes_orders_outside_active_hours(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', [
                'ask' => 1.101,
                'low' => 1.100,
                'high' => 1.110,
            ]),
            $this->candle('2026-10-09 17:30:00', [
                'bid' => 1.096,
                'low' => 1.095,
                'high' => 1.105,
            ]),
            $this->candle('2026-10-09 18:00:00', [
                'bid' => 1.0905,
                'low' => 1.089,
                'high' => 1.100,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategyClient = Mockery::mock(StrategyClient::class);
        $strategyClient->shouldReceive('execute')->once()->andReturn(
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12),
        );
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategyClient))->execute($config);
        $order = $results['order_history']->at(0);

        $this->assertSame(1, $results['results']->count());
        $this->assertSame('2026-10-09 09:00:00', $order->openDate);
        $this->assertSame('2026-10-09 18:00:00', $order->closeDate);
        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.101, $order->entryPrice);
        $this->assertSame(1.09, $order->closePrice);
        $this->assertSame([
            'price' => 1.0905,
            'date' => '2026-10-09 18:00:00',
            'source' => 'bid',
            'distance' => 0.0005,
        ], $order->closestToStopLoss);
    }

    public function test_it_closes_short_orders_at_take_profit_and_honors_close_signals(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid' => 1.099]),
            $this->candle('2026-10-09 10:00:00', [
                'ask' => 1.0805,
                'high' => 1.095,
                'low' => 1.079,
            ]),
            $this->candle('2026-10-09 11:00:00', ['ask' => 1.101]),
            $this->candle('2026-10-09 12:00:00', [
                'bid' => 1.105,
                'low' => 1.100,
                'high' => 1.110,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($pair);

        $strategyClient = Mockery::mock(StrategyClient::class);
        $strategyClient->shouldReceive('execute')->times(4)->andReturn(
            new StrategyDecision(StrategySignal::Short, 1.11, 1.08),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12),
            new StrategyDecision(StrategySignal::Close, null, null),
        );
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, $strategyClient))->execute($config);

        $this->assertSame(2, $results['order_history']->count());
        $this->assertSame('take_profit', $results['order_history']->at(0)->closeReason);
        $this->assertSame('2026-10-09 10:00:00', $results['order_history']->at(0)->closeDate);
        $this->assertSame('close', $results['order_history']->at(1)->closeReason);
        $this->assertSame('2026-10-09 12:00:00', $results['order_history']->at(1)->closeDate);
        $this->assertSame(1.105, $results['order_history']->at(1)->closePrice);
    }

    /**
     * @param  array<string, int|float|string>  $attributes
     */
    private function candle(string $timestamp, array $attributes = []): MarketPrice
    {
        return new MarketPrice(['price_date' => $timestamp, ...$attributes]);
    }
}
