<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

enum UnfilledOrderPolicy: string
{
    case Keep = 'keep';
    case Cancel = 'cancel';
}
