<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\Backtests;

use App\Feature\Backtest\Data\Analysis\BacktestPresentationData;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class BacktestPresentationDataTest extends TestCase
{
    public function test_it_round_trips_nested_external_data_without_array_state(): void
    {
        $source = [
            'candle' => [
                'price_date' => new DateTimeImmutable('2026-10-14 10:00:00'),
                'values' => [1.1, 1.2],
            ],
            'metadata' => ['pair' => 'EUR/USD'],
            'accepted' => true,
        ];

        $this->assertSame([
            'candle' => [
                'price_date' => '2026-10-14 10:00:00',
                'values' => [1.1, 1.2],
            ],
            'metadata' => ['pair' => 'EUR/USD'],
            'accepted' => true,
        ], BacktestPresentationData::fromExternal($source)->toExternal());
    }

    public function test_it_rejects_non_finite_nested_values(): void
    {
        $this->expectException(UnexpectedValueException::class);

        BacktestPresentationData::fromExternal(['metric' => INF]);
    }
}
