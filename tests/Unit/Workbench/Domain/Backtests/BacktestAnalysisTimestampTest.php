<?php

declare(strict_types=1);

namespace Tests\Unit\Workbench\Domain\Backtests;

use App\Feature\Backtest\Data\Analysis\BacktestAnalysisTimestamp;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class BacktestAnalysisTimestampTest extends TestCase
{
    public function test_it_compares_timestamp_instants_across_timezones(): void
    {
        $first = BacktestAnalysisTimestamp::fromPersistedValue('2026-10-14T10:00:00+02:00', 'timestamp');
        $second = BacktestAnalysisTimestamp::fromPersistedValue('2026-10-14T08:30:00+00:00', 'timestamp');

        $this->assertTrue($first->isBefore($second));
        $this->assertTrue($second->isAfterOrEqual($first));
        $this->assertSame('2026-10-14T10:00:00+02:00', $first->toPersistedValue());
    }

    public function test_it_rejects_invalid_calendar_dates(): void
    {
        $this->expectException(UnexpectedValueException::class);

        BacktestAnalysisTimestamp::fromPersistedValue('2026-02-30 10:00:00', 'timestamp');
    }
}
