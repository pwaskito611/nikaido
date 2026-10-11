<?php

namespace Tests\Unit\Engine\Actions;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Strategy\Config\SpecificPairTestConfig;
use App\Strategy\Strategy;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class RunSpecificPairBacktestActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_skips_inactive_candles_and_passes_them_as_history_when_active(): void
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

        $strategy = new RecordingStrategy;
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-12 23:59:59'),
            '09:00',
            '17:00',
            [6, 7],
        );

        $results = (new RunSpecificPairBacktestAction($repository))->execute($config, $strategy);

        $this->assertSame(2, $results->count());
        $this->assertSame('2026-10-09 09:00:00', $results->at(0)['candle']->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results->at(0)['result']);
        $this->assertSame('2026-10-12 10:00:00', $results->at(1)['candle']->price_date->format('Y-m-d H:i:s'));
        $this->assertSame(
            [
                '2026-10-09 08:59:00',
                '2026-10-09 09:00:00',
                '2026-10-09 17:01:00',
                '2026-10-10 10:00:00',
            ],
            $strategy->histories[1],
        );
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

        $strategy = new RecordingStrategy;
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            [6],
        );

        $results = (new RunSpecificPairBacktestAction($repository))->execute($config, $strategy);

        $this->assertSame(1, $results->count());
        $this->assertSame('2026-10-09 02:00:00', $results->at(0)['candle']->price_date->format('Y-m-d H:i:s'));
    }

    private function candle(string $timestamp): MarketPrice
    {
        return new MarketPrice(['price_date' => $timestamp]);
    }
}

class RecordingStrategy extends Strategy
{
    /** @var list<list<string>> */
    public array $histories = [];

    /**
     * @param  Collection<int, MarketPrice>  $history
     */
    public function execute(MarketPrice $candle, Collection $history): bool
    {
        $this->histories[] = $history
            ->map(fn (MarketPrice $item): string => $item->price_date->format('Y-m-d H:i:s'))
            ->all();

        return false;
    }
}
