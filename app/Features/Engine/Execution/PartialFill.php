<?php

declare(strict_types=1);

namespace App\Features\Engine\Execution;

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
}
