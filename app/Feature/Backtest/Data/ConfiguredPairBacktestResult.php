<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use DateTimeInterface;

final readonly class ConfiguredPairBacktestResult
{
    public function __construct(
        public string $pair,
        public string $timeframe,
        public DateTimeInterface $from,
        public DateTimeInterface $to,
        public SpecificPairBacktestResult $backtest,
    ) {}

    /** @return array<string, mixed> */
    public function toPersistedArray(): array
    {
        return [
            'pair' => $this->pair,
            'timeframe' => $this->timeframe,
            'from' => $this->from->format('Y-m-d H:i:s'),
            'to' => $this->to->format('Y-m-d H:i:s'),
            'initial_capital' => $this->backtest->initialCapital,
            'quantity_unit' => $this->backtest->quantityUnit,
            'units_per_lot' => $this->backtest->unitsPerLot,
            'quantity_multiplier' => $this->backtest->quantityMultiplier,
            'ending_capital' => $this->backtest->endingCapital,
            'results' => $this->backtest->decisions
                ->map(static fn (BacktestDecision $decision): array => $decision->toArray())
                ->toArray(),
            'order_history' => $this->backtest->orders
                ->map(static fn (BacktestOrderHistoryEntry $order): array => $order->jsonSerialize())
                ->toArray(),
            'execution_config' => $this->backtest->executionConfiguration->toArray(),
        ];
    }
}
