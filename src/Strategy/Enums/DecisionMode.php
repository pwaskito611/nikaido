<?php

declare(strict_types=1);

namespace Nikaido\Strategy\Enums;

enum DecisionMode: string
{
    case PerMarketPair = 'per_market_pair';
    case PortfolioTarget = 'portfolio_target';
}
