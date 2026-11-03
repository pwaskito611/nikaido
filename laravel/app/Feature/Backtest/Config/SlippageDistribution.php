<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

enum SlippageDistribution: string
{
    case Uniform = 'uniform';
    case Normal = 'normal';
}
