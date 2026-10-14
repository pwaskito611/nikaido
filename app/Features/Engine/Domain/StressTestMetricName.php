<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

enum StressTestMetricName: string
{
    case NetProfit = 'net_profit';
    case Expectancy = 'expectancy';
    case ProfitFactor = 'profit_factor';
    case MaxDrawdown = 'max_drawdown';
    case TradeCount = 'trade_count';
    case StopLossCount = 'stop_loss_count';
    case TakeProfitCount = 'take_profit_count';
    case StressTestCount = 'stress_test_count';
    case OtherExitCount = 'other_exit_count';
    case ProfitableTradeCount = 'profitable_trade_count';
    case LosingTradeCount = 'losing_trade_count';
    case BreakevenTradeCount = 'breakeven_trade_count';
    case ProfitableStressExitCount = 'profitable_stress_exit_count';
    case LosingStressExitCount = 'losing_stress_exit_count';
    case BreakevenStressExitCount = 'breakeven_stress_exit_count';
    case StopLossFrequency = 'stop_loss_frequency';
    case TakeProfitFrequency = 'take_profit_frequency';
    case StressTestFrequency = 'stress_test_frequency';
    case OtherExitFrequency = 'other_exit_frequency';
    case ProfitableTradeFrequency = 'profitable_trade_frequency';
    case LosingTradeFrequency = 'losing_trade_frequency';
    case BreakevenTradeFrequency = 'breakeven_trade_frequency';
    case ProfitableStressExitFrequency = 'profitable_stress_exit_frequency';
    case LosingStressExitFrequency = 'losing_stress_exit_frequency';
    case BreakevenStressExitFrequency = 'breakeven_stress_exit_frequency';
}
