<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\MarketTypeBacktests;

use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use App\Platform\Strategy\Enums\StrategySignal;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class PortfolioOrderDetails
{
    /**
     * @param  Option<string>  $entryPriceSource
     * @param  Option<string>  $closeReason
     * @param  Option<float>  $entryPrice
     * @param  Option<float>  $stopLoss
     * @param  Option<float>  $takeProfit
     * @param  Option<float>  $closePrice
     * @param  Option<PortfolioOrderExtreme>  $closestToStopLoss
     * @param  Option<PortfolioOrderExtreme>  $closestToTakeProfit
     * @param  Option<PortfolioOrderExtreme>  $adverseExtreme
     * @param  Option<PortfolioOrderExtreme>  $favorableExtreme
     */
    private function __construct(
        public StrategySignal $signal,
        public Option $entryPriceSource,
        public Option $closeReason,
        public Option $entryPrice,
        public Option $stopLoss,
        public Option $takeProfit,
        public Option $closePrice,
        public Option $closestToStopLoss,
        public Option $closestToTakeProfit,
        public Option $adverseExtreme,
        public Option $favorableExtreme,
        public int|float|string $strategyQuantity,
        public MarketPairQuantityUnit $quantityUnit,
        public float $quantityMultiplier,
        public float $pnlMultiplier,
        public float $capitalAtOpen,
    ) {}

    /** @param array<string, mixed> $order */
    public static function fromPersistedArray(array $order): self
    {
        $signal = $order['signal'] ?? null;
        if (! is_string($signal) || StrategySignal::tryFrom($signal) === null) {
            throw new UnexpectedValueException('Portfolio order signal must be long or short.');
        }
        $strategyQuantity = $order['strategy_quantity'] ?? null;
        if ((! is_int($strategyQuantity) && ! is_float($strategyQuantity) && ! is_string($strategyQuantity))
            || ! is_numeric($strategyQuantity)
            || ! is_finite((float) $strategyQuantity)
            || (float) $strategyQuantity <= 0) {
            throw new UnexpectedValueException('Portfolio strategy quantity must be a positive finite number.');
        }
        $quantityUnit = $order['quantity_unit'] ?? null;
        if (! is_string($quantityUnit) || MarketPairQuantityUnit::tryFrom($quantityUnit) === null) {
            throw new UnexpectedValueException('Portfolio quantity unit is unsupported.');
        }
        $quantityMultiplier = self::requiredNumber($order, 'quantity_multiplier');
        $pnlMultiplier = self::requiredNumber($order, 'pnl_multiplier');
        $capitalAtOpen = self::requiredNumber($order, 'capital_at_open');
        if ($quantityMultiplier <= 0 || $pnlMultiplier <= 0 || $capitalAtOpen <= 0) {
            throw new UnexpectedValueException('Portfolio quantity, P/L, and capital multipliers must be positive.');
        }

        return new self(
            signal: StrategySignal::from($signal),
            entryPriceSource: self::optionalString($order, 'entry_price_source'),
            closeReason: self::optionalString($order, 'close_reason'),
            entryPrice: Option::some(self::requiredNumber($order, 'entry_price')),
            stopLoss: Option::some(self::requiredNumber($order, 'stop_loss')),
            takeProfit: Option::some(self::requiredNumber($order, 'take_profit')),
            closePrice: self::optionalNumber($order, 'close_price'),
            closestToStopLoss: PortfolioOrderExtreme::fromPersistedValue(
                $order['closest_to_stop_loss'] ?? null,
                'closest_to_stop_loss',
            ),
            closestToTakeProfit: PortfolioOrderExtreme::fromPersistedValue(
                $order['closest_to_take_profit'] ?? null,
                'closest_to_take_profit',
            ),
            adverseExtreme: PortfolioOrderExtreme::fromPersistedValue($order['adverse_extreme'] ?? null, 'adverse_extreme'),
            favorableExtreme: PortfolioOrderExtreme::fromPersistedValue($order['favorable_extreme'] ?? null, 'favorable_extreme'),
            strategyQuantity: $strategyQuantity,
            quantityUnit: MarketPairQuantityUnit::from($quantityUnit),
            quantityMultiplier: $quantityMultiplier,
            pnlMultiplier: $pnlMultiplier,
            capitalAtOpen: $capitalAtOpen,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'signal' => $this->signal->value,
            'entry_price' => $this->entryPrice->getOrNull(),
            'entry_price_source' => $this->entryPriceSource->getOrNull(),
            'stop_loss' => $this->stopLoss->getOrNull(),
            'take_profit' => $this->takeProfit->getOrNull(),
            'close_reason' => $this->closeReason->getOrNull(),
            'close_price' => $this->closePrice->getOrNull(),
            'closest_to_stop_loss' => $this->closestToStopLoss->map(
                static fn (PortfolioOrderExtreme $extreme): array => $extreme->toArray(),
            )->getOrNull(),
            'closest_to_take_profit' => $this->closestToTakeProfit->map(
                static fn (PortfolioOrderExtreme $extreme): array => $extreme->toArray(),
            )->getOrNull(),
            'adverse_extreme' => $this->adverseExtreme->map(
                static fn (PortfolioOrderExtreme $extreme): array => $extreme->toArray(),
            )->getOrNull(),
            'favorable_extreme' => $this->favorableExtreme->map(
                static fn (PortfolioOrderExtreme $extreme): array => $extreme->toArray(),
            )->getOrNull(),
            'strategy_quantity' => $this->strategyQuantity,
            'quantity_unit' => $this->quantityUnit->value,
            'quantity_multiplier' => $this->quantityMultiplier,
            'pnl_multiplier' => $this->pnlMultiplier,
            'capital_at_open' => $this->capitalAtOpen,
        ];
    }

    /** @param array<string, mixed> $order */
    private static function optionalString(array $order, string $field): Option
    {
        $value = $order[$field] ?? null;
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be a string when present.");
        }

        return Option::some($value);
    }

    /** @param array<string, mixed> $order */
    private static function optionalNumber(array $order, string $field): Option
    {
        $value = $order[$field] ?? null;
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be finite when present.");
        }

        return Option::some((float) $value);
    }

    /** @param array<string, mixed> $order */
    private static function requiredNumber(array $order, string $field): float
    {
        $value = $order[$field] ?? null;
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Portfolio order {$field} must be a finite number.");
        }

        return (float) $value;
    }
}
