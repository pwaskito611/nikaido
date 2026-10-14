<?php

declare(strict_types=1);

namespace App\Features\Engine\Execution;

class FixedSlippage
{
    public function apply(float $referencePrice, float $pips, float $pipSize, bool $isBuy): float
    {
        $signedSlippage = ($isBuy ? 1 : -1) * $pips * $pipSize;

        return $referencePrice + $signedSlippage;
    }
}
