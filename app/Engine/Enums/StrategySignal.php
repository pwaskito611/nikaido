<?php

namespace App\Engine\Enums;

enum StrategySignal: string
{
    case Short = 'short';
    case Long = 'long';
    case None = 'none';
    case Close = 'close';
}
