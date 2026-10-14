<?php

declare(strict_types=1);

namespace App\Features\Engine\Config;

enum UnfilledOrderPolicy: string
{
    case Keep = 'keep';
    case Cancel = 'cancel';
}
