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
    ) {}

    /** @return array{quantity: float, price: float, date: string, source: string} */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'date' => $this->date,
            'source' => $this->source,
        ];
    }
}
