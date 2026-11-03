<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

final readonly class BacktestEntryFill
{
    public function __construct(
        public float $quantity,
        public float $price,
        public string $date,
        public string $source,
        public array $tradeCosts = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'date' => $this->date,
            'source' => $this->source,
            ...($this->tradeCosts === [] ? [] : ['trade_costs' => $this->tradeCosts]),
        ];
    }
}
