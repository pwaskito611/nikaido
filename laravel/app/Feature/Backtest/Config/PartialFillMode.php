<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

enum PartialFillMode: string
{
    case Fixed = 'fixed';
    case Volume = 'volume';
}
