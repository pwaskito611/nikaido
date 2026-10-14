<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Execution;

use App\Features\Engine\Execution\FixedSlippage;
use PHPUnit\Framework\TestCase;

class FixedSlippageTest extends TestCase
{
    public function test_it_applies_adverse_signed_slippage_for_buy_and_sell(): void
    {
        $slippage = new FixedSlippage;

        $this->assertSame(1.1002, $slippage->apply(1.1, 2, 0.0001, true));
        $this->assertSame(1.0998, $slippage->apply(1.1, 2, 0.0001, false));
    }

    public function test_it_supports_an_explicit_non_standard_pip_size(): void
    {
        $this->assertSame(
            101.5,
            (new FixedSlippage)->apply(100, 3, 0.5, true),
        );
    }

    public function test_zero_slippage_preserves_the_reference_price(): void
    {
        $this->assertSame(1.1, (new FixedSlippage)->apply(1.1, 0, 0.0001, false));
    }
}
