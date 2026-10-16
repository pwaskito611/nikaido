<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use DateTimeImmutable;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use Gauss\Number\Number;
use InvalidArgumentException;
use JsonSerializable;

class BacktestOrderHistoryEntry implements JsonSerializable
{
    /** @var Option<BacktestPriceObservation> */
    public Option $closestToStopLoss;

    /** @var Option<BacktestPriceObservation> */
    public Option $closestToTakeProfit;

    /** @var Option<BacktestPriceObservation> */
    public Option $adverseExtreme;

    /** @var Option<BacktestPriceObservation> */
    public Option $favorableExtreme;

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

    public float $grossRealizedProfitLoss = 0.0;

    /** @var array<string, float> */
    public array $tradeCostTotals = [];

    /** @var array<string, true> */
    private array $chargedOrderCosts = [];

    /** @var list<array{quantity: float, price: float, timestamp: int}> */
    private array $entryLots = [];

    /** @var Option<string> */
    public Option $pendingExitReason;

    /** @var Option<float> */
    public Option $pendingExitRequestedQuantity;

    /** @var Vec<BacktestEntryFill> */
    public Vec $entryFills;

    /** @var Vec<BacktestExitFill> */
    public Vec $exitFills;

    public function __construct(
        public readonly int $id,
        public readonly string $signal,
        public readonly string $openDate,
        public float $entryPrice,
        public string $entryPriceSource,
        public readonly float $stopLoss,
        public readonly float $takeProfit,
        public readonly int|float|string $strategyQuantity,
        public readonly string $quantityUnit,
        public readonly float $quantityMultiplier,
        public readonly float $pnlMultiplier,
        public readonly float $requestedRiskAmount,
        public float $positionSize,
        public readonly float $requestedPositionSize,
        public readonly float $capitalAtOpen,
    ) {
        $this->remainingEntryQuantity = $requestedPositionSize;
        $this->closestToStopLoss = Option::none();
        $this->closestToTakeProfit = Option::none();
        $this->adverseExtreme = Option::none();
        $this->favorableExtreme = Option::none();
        $this->pendingExitReason = Option::none();
        $this->pendingExitRequestedQuantity = Option::none();
        $this->entryFills = Vec::empty();
        $this->exitFills = Vec::empty();
        $this->tradeCostTotals = array_fill_keys([
            'commission', 'exchange_fee', 'clearing_fee', 'regulatory_fee',
            'transaction_tax', 'borrow_fee', 'financing_fee',
        ], 0.0);
    }

    public function recordEntryFill(float $quantity, float $price, string $date, string $source): void
    {
        $previousQuantity = $this->positionSize;
        $this->entryPrice = $previousQuantity === 0.0
            ? $price
            : (float) Number::of($this->entryPrice)
                ->mul($previousQuantity)
                ->add(Number::of($price)->mul($quantity))
                ->div(Number::of($previousQuantity)->add($quantity))
                ->value();
        $this->entryPriceSource = $source;
        $this->positionSize += $quantity;
        $this->remainingPositionSize += $quantity;
        $this->riskAmount = (float) Number::of($this->entryPrice)
            ->sub($this->stopLoss)
            ->abs()
            ->mul($this->positionSize)
            ->mul($this->pnlMultiplier)
            ->value();
        $this->remainingEntryQuantity = max(0.0, $this->remainingEntryQuantity - $quantity);
        $this->entryLots[] = [
            'quantity' => $quantity,
            'price' => $price,
            'timestamp' => $this->timestamp($date),
        ];
        $this->entryFills = $this->entryFills->append(new BacktestEntryFill($quantity, $price, $date, $source));
    }

    /** @param array<string, float> $tradeCosts */
    public function recordExitFill(
        float $quantity,
        float $price,
        string $date,
        string $source,
        string $reason,
        array $tradeCosts = [],
    ): float {
        $quantity = min($quantity, $this->remainingPositionSize);
        $isLong = $this->signal === 'long';
        $priceMove = $isLong
            ? Number::of($price)->sub($this->entryPrice)
            : Number::of($this->entryPrice)->sub($price);
        $fillProfitLoss = (float) $priceMove->mul($quantity)->mul($this->pnlMultiplier)->value();
        $totalCosts = $this->sumCosts($tradeCosts);
        $netProfitLoss = (float) Number::of($fillProfitLoss)->sub($totalCosts)->value();
        $this->grossRealizedProfitLoss = (float) Number::of($this->grossRealizedProfitLoss)
            ->add($fillProfitLoss)
            ->value();
        $this->realizedProfitLoss = (float) Number::of($this->realizedProfitLoss)
            ->add($netProfitLoss)
            ->value();
        $this->addTradeCosts($tradeCosts);
        $this->remainingPositionSize = max(0.0, $this->remainingPositionSize - $quantity);
        $this->closedPositionSize += $quantity;
        $this->exitFills = $this->exitFills->append(new BacktestExitFill(
            $quantity,
            $price,
            $date,
            $source,
            $reason,
            $netProfitLoss,
            Option::none(),
            $fillProfitLoss,
            array_filter($tradeCosts, static fn (float $cost): bool => $cost > 0),
        ));
        $this->closePrice = $this->closedPositionSize === $quantity
            ? $price
            : (float) Number::of($this->closePrice)
                ->mul($this->closedPositionSize - $quantity)
                ->add(Number::of($price)->mul($quantity))
                ->div($this->closedPositionSize)
                ->value();

        return $netProfitLoss;
    }

    /** @param array<string, float> $tradeCosts */
    public function recordEntryTradeCosts(array $tradeCosts): void
    {
        $totalCosts = $this->sumCosts($tradeCosts);
        $this->realizedProfitLoss = (float) Number::of($this->realizedProfitLoss)
            ->sub($totalCosts)
            ->value();
        $this->addTradeCosts($tradeCosts);
        if ($totalCosts->compare(0) <= 0) {
            return;
        }

        $lastIndex = $this->entryFills->count() - 1;
        $fill = $this->entryFills->at($lastIndex);
        $updatedFill = new BacktestEntryFill(
            $fill->quantity,
            $fill->price,
            $fill->date,
            $fill->source,
            array_filter($tradeCosts, static fn (float $cost): bool => $cost > 0),
        );
        $this->entryFills = $this->entryFills->slice(0, $lastIndex)->concat(Vec::of($updatedFill));
    }

    public function orderCostAlreadyCharged(string $component, string $side): bool
    {
        return isset($this->chargedOrderCosts["{$component}:{$side}"]);
    }

    public function markOrderCostCharged(string $component, string $side): void
    {
        $this->chargedOrderCosts["{$component}:{$side}"] = true;
    }

    public function consumeEntryNotionalDays(float $quantity, string $exitDate): float
    {
        $remaining = min($quantity, $this->remainingPositionSize);
        $exitTimestamp = $this->timestamp($exitDate);
        $notionalDaysInSeconds = Number::of(0);

        foreach ($this->entryLots as $index => $lot) {
            if ($remaining <= 0) {
                break;
            }
            $consumed = min($remaining, $lot['quantity']);
            $durationSeconds = $exitTimestamp - $lot['timestamp'];
            if ($durationSeconds < 0) {
                throw new InvalidArgumentException('An exit fill cannot precede its entry fill.');
            }
            $notionalDaysInSeconds = $notionalDaysInSeconds->add(
                Number::of($consumed)
                    ->mul($lot['price'])
                    ->mul($this->pnlMultiplier)
                    ->mul($durationSeconds),
            );
            $remaining -= $consumed;
            $lot['quantity'] -= $consumed;
            if ($lot['quantity'] <= 0.000000000001) {
                unset($this->entryLots[$index]);
            } else {
                $this->entryLots[$index] = $lot;
            }
        }
        if ($remaining > 0.000000000001) {
            throw new InvalidArgumentException('Exit quantity exceeds the remaining entry fill quantities.');
        }

        return (float) $notionalDaysInSeconds->div(86400)->value();
    }

    /** @param array<string, float> $tradeCosts */
    private function addTradeCosts(array $tradeCosts): void
    {
        foreach ($tradeCosts as $component => $cost) {
            if (! array_key_exists($component, $this->tradeCostTotals) || ! is_finite($cost) || $cost < 0) {
                throw new InvalidArgumentException("Invalid trade cost for component {$component}.");
            }
            $this->tradeCostTotals[$component] = (float) Number::of($this->tradeCostTotals[$component])
                ->add($cost)
                ->value();
        }
    }

    /** @param array<string, float> $tradeCosts */
    private function sumCosts(array $tradeCosts): Number
    {
        return array_reduce(
            $tradeCosts,
            static fn (Number $total, float $cost): Number => $total->add($cost),
            Number::of(0),
        );
    }

    private function timestamp(string $date): int
    {
        try {
            return (new DateTimeImmutable($date))->getTimestamp();
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Trade-cost calculation requires valid fill timestamps.', previous: $exception);
        }
    }

    public function recordExitCapitalAfter(float $capital): void
    {
        $lastIndex = $this->exitFills->count() - 1;
        $updatedFill = $this->exitFills->at($lastIndex)->withCapitalAfter($capital);
        $this->exitFills = $this->exitFills->slice(0, $lastIndex)
            ->concat(Vec::of($updatedFill));
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
     *     strategy_quantity: int|float|string,
     *     quantity_unit: string,
     *     quantity_multiplier: float,
     *     pnl_multiplier: float,
     *     position_size: float,
     *     capital_at_open: float,
     *     requested_position_size: float,
     *     remaining_entry_quantity: float,
     *     remaining_position_size: float,
     *     closed_position_size: float,
     *     cancelled_entry_quantity: float,
     *     cancelled_exit_quantity: float,
     *     entry_fills: list<array<string, mixed>>,
     *     exit_fills: list<array<string, mixed>>,
     *     trade_costs: array<string, float>,
     *     gross_realized_profit_loss: float,
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
            'closest_to_stop_loss' => $this->closestToStopLoss->map(
                static fn (BacktestPriceObservation $observation): array => $observation->toArray(),
            )->getOrNull(),
            'closest_to_take_profit' => $this->closestToTakeProfit->map(
                static fn (BacktestPriceObservation $observation): array => $observation->toArray(),
            )->getOrNull(),
            'adverse_extreme' => $this->adverseExtreme->map(
                static fn (BacktestPriceObservation $observation): array => $observation->toArray(),
            )->getOrNull(),
            'favorable_extreme' => $this->favorableExtreme->map(
                static fn (BacktestPriceObservation $observation): array => $observation->toArray(),
            )->getOrNull(),
            'risk_amount' => $this->riskAmount,
            'requested_risk_amount' => $this->requestedRiskAmount,
            'strategy_quantity' => $this->strategyQuantity,
            'quantity_unit' => $this->quantityUnit,
            'quantity_multiplier' => $this->quantityMultiplier,
            'pnl_multiplier' => $this->pnlMultiplier,
            'position_size' => $this->positionSize,
            'requested_position_size' => $this->requestedPositionSize,
            'remaining_entry_quantity' => $this->remainingEntryQuantity,
            'remaining_position_size' => $this->remainingPositionSize,
            'closed_position_size' => $this->closedPositionSize,
            'cancelled_entry_quantity' => $this->cancelledEntryQuantity,
            'cancelled_exit_quantity' => $this->cancelledExitQuantity,
            'entry_fills' => $this->entryFills
                ->map(static fn (BacktestEntryFill $fill): array => $fill->toArray())
                ->toArray(),
            'exit_fills' => $this->exitFills
                ->map(static fn (BacktestExitFill $fill): array => $fill->toArray())
                ->toArray(),
            'realized_profit_loss' => $this->realizedProfitLoss,
            'gross_realized_profit_loss' => $this->grossRealizedProfitLoss,
            'trade_costs' => $this->tradeCostTotals,
            'capital_at_open' => $this->capitalAtOpen,
            'profit_loss' => $this->profitLoss,
            'capital_after_close' => $this->capitalAfterClose,
        ];
    }
}
