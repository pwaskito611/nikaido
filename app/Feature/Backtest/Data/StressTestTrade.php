<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use App\Platform\Strategy\Enums\StrategySignal;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class StressTestTrade
{
    /**
     * @param  Option<StressTestExtreme>  $adverseExtreme
     * @param  Option<StressTestExtreme>  $favorableExtreme
     * @param  Option<StressTestExtreme>  $closestToStopLoss
     * @param  Option<StressTestExtreme>  $closestToTakeProfit
     * @param  Option<string>  $closeDate
     * @param  Option<string>  $closeReason
     * @param  Option<float>  $closePrice
     * @param  Option<float>  $profitLoss
     * @param  Option<float>  $closedPositionSize
     * @param  Option<string>  $quote
     */
    public function __construct(
        public StrategySignal $signal,
        public float $entryPrice,
        public float $stopLoss,
        public float $positionSize,
        public float $pnlMultiplier,
        public Option $adverseExtreme,
        public Option $favorableExtreme,
        public Option $closestToStopLoss,
        public Option $closestToTakeProfit,
        public Option $closeDate,
        public Option $closeReason,
        public Option $closePrice,
        public Option $profitLoss,
        public Option $closedPositionSize,
        public Option $quote,
        public StressTestOrderPayload $orderPayload,
    ) {}

    /**
     * Decode an order at the serialized backtest boundary.
     *
     * @param  array<string, mixed>  $order
     */
    public static function fromPersistedOrder(array $order): self
    {
        $signal = $order['signal'] ?? null;
        if (! is_string($signal)
            || ! in_array($signal, [StrategySignal::Long->value, StrategySignal::Short->value], true)) {
            throw new UnexpectedValueException('Stress-test orders must have a long or short signal.');
        }
        $quote = self::quoteOption($order['pair'] ?? null);

        foreach (['entry_price', 'stop_loss', 'position_size'] as $field) {
            if (! is_numeric($order[$field] ?? null) || ! is_finite((float) $order[$field])) {
                throw new UnexpectedValueException("Stress-test orders must have a finite {$field}.");
            }
        }
        if ((float) $order['position_size'] <= 0) {
            throw new UnexpectedValueException('Stress-test order position_size must be positive.');
        }

        $pnlMultiplier = self::finiteValue($order['pnl_multiplier'] ?? 1.0, 'pnl_multiplier');
        if ($pnlMultiplier <= 0) {
            throw new UnexpectedValueException('Stress-test order pnl_multiplier must be positive.');
        }

        return new self(
            signal: StrategySignal::from($signal),
            entryPrice: (float) $order['entry_price'],
            stopLoss: (float) $order['stop_loss'],
            positionSize: (float) $order['position_size'],
            pnlMultiplier: $pnlMultiplier,
            adverseExtreme: StressTestExtreme::fromPersistedValue($order['adverse_extreme'] ?? null, 'adverse_extreme'),
            favorableExtreme: StressTestExtreme::fromPersistedValue($order['favorable_extreme'] ?? null, 'favorable_extreme'),
            closestToStopLoss: StressTestExtreme::fromPersistedValue($order['closest_to_stop_loss'] ?? null, 'closest_to_stop_loss'),
            closestToTakeProfit: StressTestExtreme::fromPersistedValue($order['closest_to_take_profit'] ?? null, 'closest_to_take_profit'),
            closeDate: self::stringOption($order['close_date'] ?? null, 'close_date'),
            closeReason: self::stringOption($order['close_reason'] ?? null, 'close_reason'),
            closePrice: self::numericOption($order['close_price'] ?? null, 'close_price'),
            profitLoss: self::numericOption($order['profit_loss'] ?? null, 'profit_loss'),
            closedPositionSize: self::numericOption($order['closed_position_size'] ?? null, 'closed_position_size'),
            quote: $quote,
            orderPayload: StressTestOrderPayload::fromExternal($order),
        );
    }

    public function filledPositionSize(): float
    {
        return $this->closedPositionSize->getOrElse($this->positionSize);
    }

    private static function numericOption(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Stress-test order {$field} must be finite when present.");
        }

        return Option::some((float) $value);
    }

    private static function stringOption(mixed $value, string $field): Option
    {
        if ($value === null) {
            return Option::none();
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException("Stress-test order {$field} must be a string when present.");
        }

        return Option::some($value);
    }

    private static function quoteOption(mixed $pair): Option
    {
        if ($pair === null) {
            return Option::none();
        }
        if (! is_string($pair)) {
            throw new UnexpectedValueException('Stress-test order pair must be a string when present.');
        }
        if (! str_contains($pair, '/')) {
            return Option::none();
        }
        if (! preg_match('#^[^/]+/([^/]+)$#', $pair, $matches)) {
            throw new UnexpectedValueException('Stress-test order pair must contain one base/quote separator.');
        }

        return Option::some($matches[1]);
    }

    private static function finiteValue(mixed $value, string $field): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new UnexpectedValueException("Stress-test order {$field} must be finite.");
        }

        return (float) $value;
    }
}
