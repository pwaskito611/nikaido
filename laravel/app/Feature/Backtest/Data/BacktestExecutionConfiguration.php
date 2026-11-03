<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Config\TradeCostConfig;
use Exact\Type\Option\Option;

final readonly class BacktestExecutionConfiguration
{
    public function __construct(
        public FixedSlippageConfig $slippage,
        public PartialFillConfig $partialFill,
        public TradeCostConfig $tradeCosts = new TradeCostConfig,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slippage_enabled' => $this->slippage->enabled,
            'slippage_pips' => $this->slippage->pips,
            'slippage_pip_size' => Option::from($this->slippage->pipSize)->getOrNull(),
            'partial_fill_enabled' => $this->partialFill->enabled,
            'partial_fill_mode' => $this->partialFill->mode->value,
            'partial_fill_ratio' => $this->partialFill->ratio,
            'max_participation_rate' => $this->partialFill->maxParticipationRate,
            'volume_source' => $this->partialFill->volumeSource,
            'execution_volume_period' => $this->partialFill->executionVolumePeriod,
            'unfilled_order_policy' => $this->partialFill->unfilledOrderPolicy->value,
            'trade_costs' => $this->tradeCosts->toArray(),
        ];
    }
}
