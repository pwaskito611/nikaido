<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use App\Feature\MarketPrice\Model\MarketPrice;
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
        public ?string $entryOrderStatus = null,
        public ?float $entryLimitPrice = null,
    ) {}

    public function withEntryOrderStatus(?string $status): self
    {
        return new self(
            $this->candle,
            $this->entered,
            $this->signal,
            $this->quantity,
            $this->quantityUnit,
            $this->stopLoss,
            $this->takeProfit,
            $status,
            $this->entryLimitPrice,
        );
    }

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
            'entry_order_status' => $this->entryOrderStatus,
            'entry_limit_price' => $this->entryLimitPrice,
        ];
    }
}
