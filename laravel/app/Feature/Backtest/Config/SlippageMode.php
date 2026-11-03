<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

enum SlippageMode: string
{
    case Fixed = 'fixed';
    case Dynamic = 'dynamic';
    case Random = 'random';
}
