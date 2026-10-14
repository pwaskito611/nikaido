<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Config;

use App\Features\Engine\Config\SpecificPairTestConfig;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SpecificPairTestConfigTest extends TestCase
{
    public function test_it_rejects_a_non_positive_timeframe_id_without_excluded_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The timeframe type ID must be a positive integer.');

        new SpecificPairTestConfig(
            base: 'EUR',
            quote: 'USD',
            from: new DateTimeImmutable('2026-10-09 00:00:00'),
            to: new DateTimeImmutable('2026-10-09 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: 0,
        );
    }
}
