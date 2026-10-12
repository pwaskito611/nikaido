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

    /**
     * @var array{price: float, date: string, source: string}|null
     */
    public ?array $adverseExtreme = null;

    /**
     * @var array{price: float, date: string, source: string}|null
     */
    public ?array $favorableExtreme = null;

    public ?string $closeDate = null;

    public ?string $closeReason = null;

    public ?float $closePrice = null;

    public ?float $profitLoss = null;

    public ?float $capitalAfterClose = null;

    public float $riskAmount = 0.0;

    public float $remainingEntryQuantity;

    public float $remainingPositionSize = 0.0;

    public float $closedPositionSize = 0.0;

    public float $cancelledEntryQuantity = 0.0;

    public float $cancelledExitQuantity = 0.0;

    public float $realizedProfitLoss = 0.0;

    public ?string $pendingExitReason = null;

    public ?float $pendingExitRequestedQuantity = null;

    /**
     * @var list<array{quantity: float, price: float, date: string, source: string}>
     */
    public array $entryFills = [];

    /**
     * @var list<array{quantity: float, price: float, date: string, source: string, reason: string, profit_loss: float, capital_after?: float}>
     */
    public array $exitFills = [];

    public function __construct(
        public readonly int $id,
        public readonly string $signal,
        public readonly string $openDate,
        public float $entryPrice,
        public string $entryPriceSource,
        public readonly float $stopLoss,
        public readonly float $takeProfit,
        public readonly float $requestedRiskAmount,
        public float $positionSize,
        public readonly float $requestedPositionSize,
        public readonly float $capitalAtOpen,
    ) {
        $this->remainingEntryQuantity = $requestedPositionSize;
    }

    public function recordEntryFill(float $quantity, float $price, string $date, string $source): void
    {
        $previousQuantity = $this->positionSize;
        $this->entryPrice = $previousQuantity === 0.0
            ? $price
            : (($this->entryPrice * $previousQuantity) + ($price * $quantity)) / ($previousQuantity + $quantity);
        $this->entryPriceSource = $source;
        $this->positionSize += $quantity;
        $this->remainingPositionSize += $quantity;
        $this->riskAmount = abs($this->entryPrice - $this->stopLoss) * $this->positionSize;
        $this->remainingEntryQuantity = max(0.0, $this->remainingEntryQuantity - $quantity);
        $this->entryFills[] = compact('quantity', 'price', 'date', 'source');
    }

    public function recordExitFill(float $quantity, float $price, string $date, string $source, string $reason): float
    {
        $quantity = min($quantity, $this->remainingPositionSize);
        $isLong = $this->signal === 'long';
        $priceMove = $isLong ? $price - $this->entryPrice : $this->entryPrice - $price;
        $fillProfitLoss = $priceMove * $quantity;
        $this->realizedProfitLoss += $fillProfitLoss;
        $this->remainingPositionSize = max(0.0, $this->remainingPositionSize - $quantity);
        $this->closedPositionSize += $quantity;
        $this->exitFills[] = [
            ...compact('quantity', 'price', 'date', 'source', 'reason'),
            'profit_loss' => $fillProfitLoss,
        ];
        $this->closePrice = $this->closedPositionSize === $quantity
            ? $price
            : (($this->closePrice * ($this->closedPositionSize - $quantity)) + ($price * $quantity)) / $this->closedPositionSize;

        return $fillProfitLoss;
    }

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
     *     adverse_extreme: ?array{price: float, date: string, source: string},
     *     favorable_extreme: ?array{price: float, date: string, source: string},
     *     risk_amount: float,
     *     requested_risk_amount: float,
     *     position_size: float,
     *     capital_at_open: float,
     *     requested_position_size: float,
     *     remaining_entry_quantity: float,
     *     remaining_position_size: float,
     *     closed_position_size: float,
     *     cancelled_entry_quantity: float,
     *     cancelled_exit_quantity: float,
     *     entry_fills: list<array{quantity: float, price: float, date: string, source: string}>,
     *     exit_fills: list<array{quantity: float, price: float, date: string, source: string, reason: string, profit_loss: float, capital_after?: float}>,
     *     realized_profit_loss: float,
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
            'adverse_extreme' => $this->adverseExtreme,
            'favorable_extreme' => $this->favorableExtreme,
            'risk_amount' => $this->riskAmount,
            'requested_risk_amount' => $this->requestedRiskAmount,
            'position_size' => $this->positionSize,
            'requested_position_size' => $this->requestedPositionSize,
            'remaining_entry_quantity' => $this->remainingEntryQuantity,
            'remaining_position_size' => $this->remainingPositionSize,
            'closed_position_size' => $this->closedPositionSize,
            'cancelled_entry_quantity' => $this->cancelledEntryQuantity,
            'cancelled_exit_quantity' => $this->cancelledExitQuantity,
            'entry_fills' => $this->entryFills,
            'exit_fills' => $this->exitFills,
            'realized_profit_loss' => $this->realizedProfitLoss,
            'capital_at_open' => $this->capitalAtOpen,
            'profit_loss' => $this->profitLoss,
            'capital_after_close' => $this->capitalAfterClose,
        ];
    }
}
