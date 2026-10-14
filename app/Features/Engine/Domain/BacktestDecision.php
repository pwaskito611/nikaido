<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use App\Features\Engine\Models\MarketPrice;
use App\Platform\Strategy\Enums\StrategySignal;
use Exact\Type\Option\Option;

final readonly class BacktestDecision
{
    /**
     * @param  Option<int|float|string>  $quantity
     * @param  Option<float>  $stopLoss
     * @param  Option<float>  $takeProfit
     */
    public function __construct(
        public MarketPrice $candle,
        public bool $entered,
        public StrategySignal $signal,
        public Option $quantity,
        public string $quantityUnit,
        public Option $stopLoss,
        public Option $takeProfit,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'candle' => $this->candle->toArray(),
            'result' => $this->entered,
            'signal' => $this->signal->value,
            'quantity' => $this->quantity->getOrNull(),
            'quantity_unit' => $this->quantityUnit,
            'stop_loss' => $this->stopLoss->getOrNull(),
            'take_profit' => $this->takeProfit->getOrNull(),
        ];
    }
}
