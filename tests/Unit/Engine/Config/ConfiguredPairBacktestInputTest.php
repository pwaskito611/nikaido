<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Config;

use App\Features\Engine\Config\ConfiguredPairBacktestInput;
use App\Features\Engine\Config\UnfilledOrderPolicy;
use App\Features\Engine\Domain\ActiveTradingSchedule;
use Exact\Collection\Vec;
use PHPUnit\Framework\TestCase;

class ConfiguredPairBacktestInputTest extends TestCase
{
    public function test_it_adapts_validated_and_persisted_values_to_exact_backtest_input(): void
    {
        $input = ConfiguredPairBacktestInput::fromArray([
            'market_pair_id' => '12',
            'timeframe_type_id' => '4',
            'initial_capital' => '2500.50',
            'from' => '2026-10-10 09:00:00',
            'to' => '2026-10-11 17:00:00',
            'active_from' => '09:00',
            'active_until' => '17:00',
            'excluded_days' => ['6', '7'],
            'slippage_enabled' => '1',
            'slippage_pips' => '1.5',
            'slippage_pip_size' => '0.0001',
            'partial_fill_enabled' => true,
            'partial_fill_ratio' => '0.5',
            'unfilled_order_policy' => 'cancel',
        ]);

        $this->assertSame(12, $input->marketPairId);
        $this->assertSame(4, $input->timeframeTypeId);
        $this->assertSame(2500.5, $input->initialCapital);
        $this->assertInstanceOf(ActiveTradingSchedule::class, $input->activeSchedule);
        $this->assertInstanceOf(Vec::class, $input->excludedDays);
        $this->assertSame([6, 7], $input->excludedDays->toArray());
        $this->assertTrue($input->slippage->enabled);
        $this->assertSame(1.5, $input->slippage->pips);
        $this->assertSame(0.0001, $input->slippage->pipSize);
        $this->assertTrue($input->partialFill->enabled);
        $this->assertSame(0.5, $input->partialFill->ratio);
        $this->assertSame(UnfilledOrderPolicy::Cancel, $input->partialFill->unfilledOrderPolicy);
    }

    public function test_it_applies_execution_defaults_when_optional_configuration_is_absent(): void
    {
        $input = ConfiguredPairBacktestInput::fromArray([
            'market_pair_id' => 12,
            'timeframe_type_id' => 4,
            'initial_capital' => 1000,
            'from' => '2026-10-10 00:00:00',
            'to' => '2026-10-10 23:59:59',
            'active_from' => false,
            'active_until' => false,
        ]);

        $this->assertFalse($input->slippage->enabled);
        $this->assertSame(0.0, $input->slippage->pips);
        $this->assertNull($input->slippage->pipSize);
        $this->assertFalse($input->partialFill->enabled);
        $this->assertSame(1.0, $input->partialFill->ratio);
        $this->assertSame(UnfilledOrderPolicy::Keep, $input->partialFill->unfilledOrderPolicy);
        $this->assertTrue($input->excludedDays->isEmpty());
    }
}
