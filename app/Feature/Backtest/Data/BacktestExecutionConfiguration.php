<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use Exact\Type\Option\Option;

final readonly class BacktestExecutionConfiguration
{
    public function __construct(
        public FixedSlippageConfig $slippage,
        public PartialFillConfig $partialFill,
    ) {}

    /** @return array<string, bool|float|string|null> */
    public function toArray(): array
    {
        return [
            'slippage_enabled' => $this->slippage->enabled,
            'slippage_pips' => $this->slippage->pips,
            'slippage_pip_size' => Option::from($this->slippage->pipSize)->getOrNull(),
            'partial_fill_enabled' => $this->partialFill->enabled,
            'partial_fill_ratio' => $this->partialFill->ratio,
            'unfilled_order_policy' => $this->partialFill->unfilledOrderPolicy->value,
        ];
    }
}
