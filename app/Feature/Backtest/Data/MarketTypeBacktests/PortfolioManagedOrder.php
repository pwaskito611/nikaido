<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\MarketTypeBacktests;

use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use Gauss\Number\Number;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class PortfolioManagedOrder
{
    /**
     * @param  Vec<PortfolioEntryFill>  $entryFills
     * @param  Vec<PortfolioExitFill>  $exitFills
     */
    private function __construct(
        public int $index,
        public string $pair,
        public int $id,
        public string $openDate,
        public Option $closeDate,
        public float $requestedPositionSize,
        public float $requestedRiskAmount,
        public float $riskAmount,
        public float $cancelledEntryQuantity,
        public Option $profitLoss,
        public float $positionSize,
        public float $remainingEntryQuantity,
        public float $remainingPositionSize,
        public float $closedPositionSize,
        public float $cancelledExitQuantity,
        public float $realizedProfitLoss,
        public float $grossRealizedProfitLoss,
        public array $tradeCostTotals,
        public Vec $entryFills,
        public Vec $exitFills,
        public Option $allocationFactor,
        public Option $portfolioRequestedRiskAmount,
        public Option $portfolioAllocatedRiskAmount,
        public Option $capitalAtOpen,
        public Option $capitalAfterClose,
        public bool $entryRejected,
        public PortfolioOrderDetails $details,
    ) {}

    /**
     * Convert a persisted order only at the orchestration boundary.
     *
     * @param  array<string, mixed>  $order
     */
    public static function fromPersistedArray(array $order, int $index): self
    {
        $openDate = $order['open_date'] ?? null;
        if (! is_string($openDate) || strtotime($openDate) === false) {
            throw new UnexpectedValueException('A portfolio-managed order must have a valid opening date.');
        }

        $pair = $order['pair'] ?? null;
        if (! is_string($pair) || $pair === '') {
            throw new UnexpectedValueException('A portfolio-managed order must have a pair name.');
        }
        $id = $order['id'] ?? null;
        if (! is_numeric($id) || (int) $id < 1) {
            throw new UnexpectedValueException('A portfolio-managed order must have a positive identifier.');
        }

        $requestedPositionSize = self::positiveNumber($order, 'requested_position_size');
        $requestedRiskAmount = self::positiveNumber($order, 'requested_risk_amount');
        $closeDate = $order['close_date'] ?? null;
        if ($closeDate !== null && (! is_string($closeDate) || strtotime($closeDate) === false)) {
            throw new UnexpectedValueException('Portfolio-managed order closing dates must be valid or absent.');
        }

        $entryFills = self::entryFills($order['entry_fills'] ?? []);
        $exitFills = self::exitFills($order['exit_fills'] ?? []);
        $entryRejected = $order['portfolio_entry_rejected'] ?? false;
        if (! is_bool($entryRejected)) {
            throw new UnexpectedValueException('Portfolio entry-rejected state must be boolean.');
        }

        $tradeCostTotals = self::costs($order['trade_costs'] ?? []);
        $realizedProfitLoss = self::optionalNumber($order, 'realized_profit_loss')->getOrElse(0.0);

        return new self(
            index: $index,
            pair: $pair,
            id: (int) $id,
            openDate: $openDate,
            closeDate: Option::from($closeDate),
            requestedPositionSize: $requestedPositionSize,
            requestedRiskAmount: $requestedRiskAmount,
            riskAmount: self::nonNegativeNumber($order, 'risk_amount'),
            cancelledEntryQuantity: self::nonNegativeNumber($order, 'cancelled_entry_quantity'),
            profitLoss: self::optionalNumber($order, 'profit_loss'),
            positionSize: self::nonNegativeNumber($order, 'position_size'),
            remainingEntryQuantity: self::nonNegativeNumber($order, 'remaining_entry_quantity'),
            remainingPositionSize: self::nonNegativeNumber($order, 'remaining_position_size'),
            closedPositionSize: self::nonNegativeNumber($order, 'closed_position_size'),
            cancelledExitQuantity: self::nonNegativeNumber($order, 'cancelled_exit_quantity'),
            realizedProfitLoss: $realizedProfitLoss,
            grossRealizedProfitLoss: self::optionalNumber($order, 'gross_realized_profit_loss')
                ->getOrElse((float) Number::of($realizedProfitLoss)->add(self::sumCosts($tradeCostTotals))->value()),
            tradeCostTotals: $tradeCostTotals,
            entryFills: $entryFills,
            exitFills: $exitFills,
            allocationFactor: self::optionalNumber($order, 'portfolio_allocation_factor'),
            portfolioRequestedRiskAmount: self::optionalNumber($order, 'portfolio_requested_risk_amount'),
            portfolioAllocatedRiskAmount: self::optionalNumber($order, 'portfolio_allocated_risk_amount'),
            capitalAtOpen: self::optionalNumber($order, 'capital_at_open'),
            capitalAfterClose: self::optionalNumber($order, 'capital_after_close'),
            entryRejected: $entryRejected,
            details: PortfolioOrderDetails::fromPersistedArray($order),
        );
    }

    public function riskPerUnit(): float
    {
        return $this->requestedRiskAmount / $this->requestedPositionSize;
    }

    /** @param list<string> $perOrderCostComponents */
    public function withAllocation(
        float $factor,
        float $requestedRisk,
        float $allocatedRisk,
        float $capital,
        array $perOrderCostComponents = [],
    ): self {
        $tradeCosts = $this->tradeCostTotals;
        foreach ($tradeCosts as $component => $amount) {
            if (! in_array($component, $perOrderCostComponents, true)) {
                $tradeCosts[$component] = $amount * $factor;
            }
        }
        $grossProfitLoss = $this->grossRealizedProfitLoss * $factor;
        $totalCosts = self::sumCosts($tradeCosts);
        $netProfitLoss = (float) Number::of($grossProfitLoss)->sub($totalCosts)->value();

        return $this->copy(
            positionSize: $this->positionSize * $factor,
            riskAmount: $this->riskAmount * $factor,
            remainingEntryQuantity: $this->remainingEntryQuantity * $factor,
            remainingPositionSize: $this->remainingPositionSize * $factor,
            closedPositionSize: $this->closedPositionSize * $factor,
            cancelledEntryQuantity: $this->cancelledEntryQuantity * $factor,
            cancelledExitQuantity: $this->cancelledExitQuantity * $factor,
            realizedProfitLoss: $netProfitLoss,
            grossRealizedProfitLoss: $grossProfitLoss,
            tradeCostTotals: $tradeCosts,
            profitLoss: $this->profitLoss->map(fn (float $_): float => $netProfitLoss),
            entryFills: $this->entryFills->map(
                fn (PortfolioEntryFill $fill): PortfolioEntryFill => $fill->scaled($factor, $perOrderCostComponents),
            ),
            exitFills: $this->exitFills->map(
                fn (PortfolioExitFill $fill): PortfolioExitFill => $fill->scaled($factor, $perOrderCostComponents),
            ),
            allocationFactor: Option::some($factor),
            portfolioRequestedRiskAmount: Option::some($requestedRisk),
            portfolioAllocatedRiskAmount: Option::some($allocatedRisk),
            capitalAtOpen: Option::some($capital),
        );
    }

    public function withExitCapital(int $fillIndex, float $capital, bool $closeOrder): self
    {
        return $this->copy(
            exitFills: $this->replaceExitFill(
                $this->exitFills,
                $fillIndex,
                $this->exitFills->at($fillIndex)->withCapitalAfter($capital),
            ),
            capitalAfterClose: $closeOrder ? Option::some($capital) : $this->capitalAfterClose,
        );
    }

    public function withCapitalAfterClose(float $capital): self
    {
        return $this->copy(capitalAfterClose: Option::some($capital));
    }

    /** @return array<string, float> */
    public function entryTradeCosts(): array
    {
        return $this->entryFills->foldLeft([], static function (array $totals, PortfolioEntryFill $fill): array {
            foreach ($fill->tradeCosts as $component => $amount) {
                $totals[$component] = ($totals[$component] ?? 0.0) + $amount;
            }

            return $totals;
        });
    }

    public function rejected(): self
    {
        return $this->copy(
            profitLoss: Option::none(),
            positionSize: 0.0,
            riskAmount: 0.0,
            remainingEntryQuantity: 0.0,
            remainingPositionSize: 0.0,
            closedPositionSize: 0.0,
            realizedProfitLoss: 0.0,
            grossRealizedProfitLoss: 0.0,
            tradeCostTotals: [],
            entryFills: Vec::empty(),
            exitFills: Vec::empty(),
            entryRejected: true,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $order = [
            'id' => $this->id,
            'pair' => $this->pair,
            'open_date' => $this->openDate,
            'close_date' => $this->closeDate->getOrNull(),
            ...$this->details->toArray(),
            'risk_amount' => $this->riskAmount,
            'requested_risk_amount' => $this->requestedRiskAmount,
            'requested_position_size' => $this->requestedPositionSize,
            'position_size' => $this->positionSize,
            'remaining_entry_quantity' => $this->remainingEntryQuantity,
            'remaining_position_size' => $this->remainingPositionSize,
            'closed_position_size' => $this->closedPositionSize,
            'cancelled_entry_quantity' => $this->cancelledEntryQuantity,
            'cancelled_exit_quantity' => $this->cancelledExitQuantity,
            'entry_fills' => $this->entryFills
                ->map(static fn (PortfolioEntryFill $fill): array => $fill->toArray())
                ->toArray(),
            'exit_fills' => $this->exitFills
                ->map(static fn (PortfolioExitFill $fill): array => $fill->toArray())
                ->toArray(),
            'realized_profit_loss' => $this->realizedProfitLoss,
            'gross_realized_profit_loss' => $this->grossRealizedProfitLoss,
            'trade_costs' => $this->tradeCostTotals,
            'profit_loss' => $this->profitLoss->getOrNull(),
            'capital_after_close' => $this->capitalAfterClose->getOrNull(),
        ];
        if ($this->allocationFactor->isSome()) {
            $order['portfolio_allocation_factor'] = $this->allocationFactor->getOrNull();
            $order['portfolio_requested_risk_amount'] = $this->portfolioRequestedRiskAmount->getOrNull();
            $order['portfolio_allocated_risk_amount'] = $this->portfolioAllocatedRiskAmount->getOrNull();
        }
        if ($this->capitalAtOpen->isSome()) {
            $order['capital_at_open'] = $this->capitalAtOpen->getOrNull();
        }
        if ($this->capitalAfterClose->isSome()) {
            $order['capital_after_close'] = $this->capitalAfterClose->getOrNull();
        }
        if ($this->entryRejected) {
            $order['portfolio_entry_rejected'] = true;
            $order['entry_status'] = 'cancelled';
            $order['close_date'] = null;
            $order['close_reason'] = 'portfolio_capital_unavailable';
            $order['close_price'] = null;
            $order['profit_loss'] = null;
        }

        return $order;
    }

    /** @param array<string, mixed> $order */
    private static function positiveNumber(array $order, string $field): float
    {
        $value = $order[$field] ?? null;
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new InvalidArgumentException("A portfolio-managed order must have a positive numeric {$field}.");
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $order */
    private static function nonNegativeNumber(array $order, string $field): float
    {
        $value = $order[$field] ?? 0.0;
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException("A portfolio-managed order must have a non-negative numeric {$field}.");
        }

        return (float) $value;
    }

    /** @param array<string, mixed> $order */
    private static function optionalNumber(array $order, string $field): Option
    {
        $value = $order[$field] ?? null;
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be a finite number when present.");
        }

        return Option::some((float) $value);
    }

    /** @return array<string, float> */
    private static function costs(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UnexpectedValueException('Portfolio trade costs must be a record.');
        }
        foreach ($value as $component => $amount) {
            if (! is_string($component) || ! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount < 0) {
                throw new UnexpectedValueException('Portfolio trade costs must contain finite non-negative amounts.');
            }
            $value[$component] = (float) $amount;
        }

        return $value;
    }

    /** @return Vec<PortfolioEntryFill> */
    private static function entryFills(mixed $records): Vec
    {
        if (! is_array($records) || ! array_is_list($records)) {
            throw new UnexpectedValueException('Portfolio entry fills must be a list.');
        }

        $fills = Vec::empty();
        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new UnexpectedValueException('Every portfolio entry fill must be a record.');
            }
            $fills = $fills->append(PortfolioEntryFill::fromPersistedArray($record));
        }

        return $fills;
    }

    /** @return Vec<PortfolioExitFill> */
    private static function exitFills(mixed $records): Vec
    {
        if (! is_array($records) || ! array_is_list($records)) {
            throw new UnexpectedValueException('Portfolio exit fills must be a list.');
        }

        $fills = Vec::empty();
        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new UnexpectedValueException('Every portfolio exit fill must be a record.');
            }
            $fills = $fills->append(PortfolioExitFill::fromPersistedArray($record));
        }

        return $fills;
    }

    private function copy(
        ?float $positionSize = null,
        ?float $riskAmount = null,
        ?float $remainingEntryQuantity = null,
        ?float $remainingPositionSize = null,
        ?float $closedPositionSize = null,
        ?float $cancelledEntryQuantity = null,
        ?float $cancelledExitQuantity = null,
        ?float $realizedProfitLoss = null,
        ?float $grossRealizedProfitLoss = null,
        ?array $tradeCostTotals = null,
        ?Option $profitLoss = null,
        ?Vec $entryFills = null,
        ?Vec $exitFills = null,
        ?Option $allocationFactor = null,
        ?Option $portfolioRequestedRiskAmount = null,
        ?Option $portfolioAllocatedRiskAmount = null,
        ?Option $capitalAtOpen = null,
        ?Option $capitalAfterClose = null,
        ?bool $entryRejected = null,
    ): self {
        return new self(
            $this->index,
            $this->pair,
            $this->id,
            $this->openDate,
            $this->closeDate,
            $this->requestedPositionSize,
            $this->requestedRiskAmount,
            $riskAmount ?? $this->riskAmount,
            $cancelledEntryQuantity ?? $this->cancelledEntryQuantity,
            $profitLoss ?? $this->profitLoss,
            $positionSize ?? $this->positionSize,
            $remainingEntryQuantity ?? $this->remainingEntryQuantity,
            $remainingPositionSize ?? $this->remainingPositionSize,
            $closedPositionSize ?? $this->closedPositionSize,
            $cancelledExitQuantity ?? $this->cancelledExitQuantity,
            $realizedProfitLoss ?? $this->realizedProfitLoss,
            $grossRealizedProfitLoss ?? $this->grossRealizedProfitLoss,
            $tradeCostTotals ?? $this->tradeCostTotals,
            $entryFills ?? $this->entryFills,
            $exitFills ?? $this->exitFills,
            $allocationFactor ?? $this->allocationFactor,
            $portfolioRequestedRiskAmount ?? $this->portfolioRequestedRiskAmount,
            $portfolioAllocatedRiskAmount ?? $this->portfolioAllocatedRiskAmount,
            $capitalAtOpen ?? $this->capitalAtOpen,
            $capitalAfterClose ?? $this->capitalAfterClose,
            $entryRejected ?? $this->entryRejected,
            $this->details,
        );
    }

    /**
     * @param  Vec<PortfolioExitFill>  $items
     * @return Vec<PortfolioExitFill>
     */
    private function replaceExitFill(Vec $items, int $index, PortfolioExitFill $fill): Vec
    {
        return $items->slice(0, $index)
            ->concat(Vec::of($fill))
            ->concat($items->slice($index + 1));
    }

    /** @param array<string, float> $costs */
    private static function sumCosts(array $costs): Number
    {
        return array_reduce(
            $costs,
            static fn (Number $total, float $cost): Number => $total->add($cost),
            Number::of(0),
        );
    }
}
