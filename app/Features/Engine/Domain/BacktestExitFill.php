<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

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
        );
    }

    /** @return array{quantity: float, price: float, date: string, source: string, reason: string, profit_loss: float, capital_after?: float} */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'price' => $this->price,
            'date' => $this->date,
            'source' => $this->source,
            'reason' => $this->reason,
            'profit_loss' => $this->profitLoss,
            ...($this->capitalAfter->isSome()
                ? ['capital_after' => $this->capitalAfter->getOrElse(0.0)]
                : []),
        ];
    }
}
