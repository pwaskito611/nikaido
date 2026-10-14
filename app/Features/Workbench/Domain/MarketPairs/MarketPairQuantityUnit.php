<?php

declare(strict_types=1);

namespace App\Features\Workbench\Domain\MarketPairs;

enum MarketPairQuantityUnit: string
{
    case Units = 'units';
    case Contract = 'contract';
    case Lot = 'lot';
}
