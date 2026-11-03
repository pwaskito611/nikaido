<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Data;

enum MarketPairQuantityUnit: string
{
    case Units = 'units';
    case Contract = 'contract';
    case Lot = 'lot';
}
