<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Execution;

use App\Engine\Execution\PartialFill;
use PHPUnit\Framework\TestCase;

class PartialFillTest extends TestCase
{
    public function test_it_fills_the_configured_fraction_of_the_requested_quantity(): void
    {
        $this->assertSame(25.0, (new PartialFill)->quantity(100, 100, 0.25));
    }

    public function test_it_caps_fill_at_remaining_quantity(): void
    {
        $this->assertSame(20.0, (new PartialFill)->quantity(100, 20, 0.5));
    }

    public function test_full_fill_ratio_fills_all_remaining_quantity(): void
    {
        $this->assertSame(73.0, (new PartialFill)->quantity(100, 73, 1));
    }
}
