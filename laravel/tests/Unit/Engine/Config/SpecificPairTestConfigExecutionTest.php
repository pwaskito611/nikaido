<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Config;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SpecificPairTestConfigExecutionTest extends TestCase
{
    public function test_execution_simulations_default_to_disabled(): void
    {
        $config = $this->config();

        $this->assertFalse($config->slippage->enabled);
        $this->assertFalse($config->partialFill->enabled);
    }

    public function test_it_rejects_invalid_partial_fill_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PartialFillConfig(enabled: true, ratio: 0);
    }

    public function test_it_rejects_invalid_pip_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FixedSlippageConfig(enabled: true, pipSize: 0);
    }

    private function config(): SpecificPairTestConfig
    {
        return new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-01-01 00:00:00'),
            new DateTimeImmutable('2026-01-02 00:00:00'),
            false,
            false,
        );
    }
}
