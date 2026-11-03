<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Execution;

use Gauss\Number\Number;

class PartialFill
{
    public function quantity(Number|int|float|string $requestedQuantity, Number|int|float|string $remainingQuantity, int|float|string $fillRatio): float
    {
        $calculatedQuantity = Number::of($requestedQuantity)->mul(Number::of($fillRatio));
        $remaining = Number::of($remainingQuantity);

        return (float) ($remaining->compare($calculatedQuantity) <= 0
            ? $remaining->value()
            : $calculatedQuantity->value());
    }

    public function quantityFromCapacity(
        Number|int|float|string $requestedQuantity,
        Number|int|float|string $remainingQuantity,
        Number|int|float|string $executionCapacity,
    ): float {
        $requested = Number::of($requestedQuantity);
        $remaining = Number::of($remainingQuantity);
        $capacity = Number::of($executionCapacity);
        $maximum = $remaining->compare($requested) <= 0 ? $remaining : $requested;

        return (float) ($capacity->compare($maximum) < 0
            ? $capacity->value()
            : $maximum->value());
    }
}
