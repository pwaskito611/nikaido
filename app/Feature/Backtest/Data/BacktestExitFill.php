<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use Exact\Type\Option\Option;

final readonly class BacktestExitFill
{
    /**
     * @param  Option<float>  $capitalAfter
     */
    public function __construct(
        public float $quantity,
        public float $price,
        public string $date,
        public string $source,
        public string $reason,
        public float $profitLoss,
        public Option $capitalAfter,
        public float $grossProfitLoss = 0.0,
        public array $tradeCosts = [],
    ) {}

    public function withCapitalAfter(float $capital): self
    {
        return new self(
            $this->quantity,
            $this->price,
            $this->date,
            $this->source,
            $this->reason,
            $this->profitLoss,
            Option::some($capital),
            $this->grossProfitLoss,
            $this->tradeCosts,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'date' => $this->date,
            'source' => $this->source,
            'reason' => $this->reason,
            'profit_loss' => $this->profitLoss,
            ...($this->tradeCosts === [] ? [] : [
                'gross_profit_loss' => $this->grossProfitLoss,
                'trade_costs' => $this->tradeCosts,
            ]),
            ...($this->capitalAfter->isSome()
                ? ['capital_after' => $this->capitalAfter->getOrElse(0.0)]
                : []),
        ];
    }
}
