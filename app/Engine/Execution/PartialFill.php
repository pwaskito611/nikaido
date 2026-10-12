<?php

declare(strict_types=1);

namespace App\Engine\Execution;

class PartialFill
{
    public function quantity(float $requestedQuantity, float $remainingQuantity, float $fillRatio): float
    {
        return min($remainingQuantity, $requestedQuantity * $fillRatio);
    }
}
