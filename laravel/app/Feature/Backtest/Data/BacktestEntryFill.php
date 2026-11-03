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
        public ?float $requestedQuantity = null,
        public ?float $remainingQuantity = null,
        public ?float $fillRatio = null,
        public string $status = 'filled',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'date' => $this->date,
            'source' => $this->source,
            'requested_quantity' => $this->requestedQuantity ?? $this->quantity,
            'remaining_quantity' => $this->remainingQuantity ?? 0.0,
            'fill_ratio' => $this->fillRatio ?? 1.0,
            'status' => $this->status,
            ...($this->tradeCosts === [] ? [] : ['trade_costs' => $this->tradeCosts]),
        ];
    }
}
