<?php

declare(strict_types=1);

namespace Tests\Unit\Strategy;

use DateTimeImmutable;
use Nikaido\Strategy\Models\FundamentalDataCollection;
use Nikaido\Strategy\Models\FundamentalEntityData;
use Nikaido\Strategy\Models\FundamentalObservation;
use PHPUnit\Framework\TestCase;

class FundamentalEntityDataTest extends TestCase
{
    public function test_it_returns_only_observations_released_by_the_requested_time(): void
    {
        $series = $this->series([
            $this->observation('CPI', '2024-08-01', '2024-09-10 08:30:00', 1.0),
            $this->observation('CPI', '2024-09-01', '2024-10-10 08:30:00', 1.1),
            $this->observation('CPI', '2024-10-01', '2024-10-25 08:30:00', 1.2),
        ]);

        $knownAt = new DateTimeImmutable('2024-10-20 23:59:59');
        $observations = $series->observations('cpi', $knownAt);

        $this->assertCount(2, $observations);
        $this->assertSame([1.0, 1.1], array_map(
            static fn (FundamentalObservation $observation): float => (float) $observation->value,
            $observations,
        ));
        $this->assertSame(1.1, $series->latestAvailable('CPI', $knownAt)?->value);
        $this->assertTrue($series->has('CPI', $knownAt));
    }

    public function test_latest_available_uses_observation_period_not_latest_release_timestamp(): void
    {
        $series = $this->series([
            $this->observation('CPI', '2024-09-01', '2024-10-20 08:30:00', 1.1),
            $this->observation('CPI', '2024-08-01', '2024-10-21 08:30:00', 1.0),
        ]);

        $latest = $series->latestAvailable('CPI', new DateTimeImmutable('2024-10-22 00:00:00'));

        $this->assertSame('2024-09-01', $latest?->datetime->format('Y-m-d'));
        $this->assertSame(1.1, $latest?->value);
    }

    public function test_value_at_requires_an_exact_period_and_release_by_knowledge_time(): void
    {
        $series = $this->series([
            $this->observation('GDP', '2024-07-01', '2024-09-20 12:00:00', ['growth' => 2.4]),
            $this->observation('GDP', '2024-10-01', '2024-11-20 12:00:00', ['growth' => 2.7]),
        ]);

        $this->assertNull($series->valueAt(
            'GDP',
            new DateTimeImmutable('2024-10-01'),
            new DateTimeImmutable('2024-10-20'),
        ));
        $value = $series->valueAt(
            'gdp',
            new DateTimeImmutable('2024-07-01'),
            new DateTimeImmutable('2024-10-20'),
        );
        $this->assertSame(['growth' => 2.4], $value?->value);
        $this->assertNull($series->valueAt(
            'GDP',
            new DateTimeImmutable('2024-07-01 12:00:00'),
            new DateTimeImmutable('2024-10-20'),
        ));
    }

    public function test_missing_indicators_return_empty_or_null_results(): void
    {
        $series = $this->series([
            $this->observation('CPI', '2024-08-01', '2024-09-10 08:30:00', 1.0),
        ]);
        $knownAt = new DateTimeImmutable('2024-10-20');

        $this->assertFalse($series->has('GDP', $knownAt));
        $this->assertSame([], $series->observations('GDP', $knownAt));
        $this->assertNull($series->latestAvailable('GDP', $knownAt));
    }

    public function test_fundamental_collection_is_keyed_by_entity_id(): void
    {
        $first = $this->series([], 4);
        $second = $this->series([], 8);
        $collection = new FundamentalDataCollection([
            4 => $first,
            8 => $second,
        ]);

        $this->assertTrue($collection->has(4));
        $this->assertSame($first, $collection->get(4));
        $this->assertSame([4, 8], array_keys($collection->all()));
        $this->assertFalse($collection->has(99));
        $this->assertNull($collection->get(99));
    }

    /**
     * @param  list<FundamentalObservation>  $observations
     */
    private function series(array $observations, int $id = 4): FundamentalEntityData
    {
        return new FundamentalEntityData(
            fundamentalId: $id,
            name: 'United States Economy',
            category: 'Economy',
            description: null,
            observations: $observations,
        );
    }

    private function observation(
        string $code,
        string $period,
        string $releasedAt,
        array|bool|float|int|string|null $value,
    ): FundamentalObservation {
        return new FundamentalObservation(
            code: $code,
            name: $code,
            datetime: new DateTimeImmutable($period),
            releasedAt: new DateTimeImmutable($releasedAt),
            value: $value,
        );
    }
}
