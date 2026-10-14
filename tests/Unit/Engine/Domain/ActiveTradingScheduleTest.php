<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Domain;

use App\Features\Engine\Domain\ActiveTradingSchedule;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActiveTradingScheduleTest extends TestCase
{
    public function test_full_day_schedule_is_active_at_any_time(): void
    {
        $schedule = ActiveTradingSchedule::fullDay();

        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-13 00:00:00')));
        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-13 23:59:00')));
    }

    public function test_window_includes_both_boundaries(): void
    {
        $schedule = ActiveTradingSchedule::between('09:00', '17:00');

        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-13 09:00:00')));
        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-13 17:00:00')));
        $this->assertFalse($schedule->includes(new DateTimeImmutable('2026-10-13 17:01:00')));
    }

    public function test_window_supports_overnight_sessions(): void
    {
        $schedule = ActiveTradingSchedule::between('22:00', '02:00');

        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-13 23:00:00')));
        $this->assertTrue($schedule->includes(new DateTimeImmutable('2026-10-14 01:00:00')));
        $this->assertFalse($schedule->includes(new DateTimeImmutable('2026-10-14 12:00:00')));
    }

    public function test_it_rejects_invalid_time_windows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ActiveTradingSchedule::between('24:00', '02:00');
    }
}
