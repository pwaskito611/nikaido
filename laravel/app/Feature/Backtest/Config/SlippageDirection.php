<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

enum SlippageDirection: string
{
    case AdverseOnly = 'adverse_only';
    case Random = 'random';
}
