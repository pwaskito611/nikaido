<?php

declare(strict_types=1);

namespace App\Engine\Models;

use JsonSerializable;

class BacktestOrderHistoryEntry implements JsonSerializable
{
    /**
     * @var array{price: float, date: string, source: string, distance: float}|null
     */
    public ?array $closestToStopLoss = null;

    /**
     * @var array{price: float, date: string, source: string, distance: float}|null
     */
    public ?array $closestToTakeProfit = null;

    public ?string $closeDate = null;

    public ?string $closeReason = null;

    public ?float $closePrice = null;

    public ?float $profitLoss = null;

    public ?float $capitalAfterClose = null;

    public function __construct(
        public readonly int $id,
        public readonly string $signal,
        public readonly string $openDate,
        public readonly float $entryPrice,
        public readonly string $entryPriceSource,
        public readonly float $stopLoss,
        public readonly float $takeProfit,
        public readonly float $riskAmount,
        public readonly float $positionSize,
        public readonly float $capitalAtOpen,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     signal: string,
     *     open_date: string,
     *     close_date: ?string,
     *     entry_price: float,
     *     entry_price_source: string,
     *     stop_loss: float,
     *     take_profit: float,
     *     close_reason: ?string,
     *     close_price: ?float,
     *     closest_to_stop_loss: ?array{price: float, date: string, source: string, distance: float},
     *     closest_to_take_profit: ?array{price: float, date: string, source: string, distance: float},
     *     risk_amount: float,
     *     position_size: float,
     *     capital_at_open: float,
     *     profit_loss: ?float,
     *     capital_after_close: ?float
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'signal' => $this->signal,
            'open_date' => $this->openDate,
            'close_date' => $this->closeDate,
            'entry_price' => $this->entryPrice,
            'entry_price_source' => $this->entryPriceSource,
            'stop_loss' => $this->stopLoss,
            'take_profit' => $this->takeProfit,
            'close_reason' => $this->closeReason,
            'close_price' => $this->closePrice,
            'closest_to_stop_loss' => $this->closestToStopLoss,
            'closest_to_take_profit' => $this->closestToTakeProfit,
            'risk_amount' => $this->riskAmount,
            'position_size' => $this->positionSize,
            'capital_at_open' => $this->capitalAtOpen,
            'profit_loss' => $this->profitLoss,
            'capital_after_close' => $this->capitalAfterClose,
        ];
    }
}
