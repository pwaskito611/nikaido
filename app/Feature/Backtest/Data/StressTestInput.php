<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class StressTestInput
{
    /**
     * @param  Vec<StressTestTrade>  $trades
     * @param  Option<string>  $quote
     */
    public function __construct(
        public float $initialCapital,
        public Vec $trades,
        public FixedSlippageConfig $slippage,
        public Option $quote,
    ) {
        if (! is_finite($initialCapital)) {
            throw new UnexpectedValueException('Stress-test initial capital must be finite.');
        }
    }

    /**
     * Decode the persisted backtest payload before entering the domain.
     *
     * @param  array<string, mixed>  $backtest
     */
    public static function fromPersistedBacktest(array $backtest): self
    {
        $initialCapital = $backtest['initial_capital'] ?? null;
        $orders = $backtest['order_history'] ?? null;
        if (! is_numeric($initialCapital) || ! is_finite((float) $initialCapital)) {
            throw new UnexpectedValueException('Stress-test input must contain finite initial capital.');
        }
        if (! is_array($orders) || ! array_is_list($orders)) {
            throw new UnexpectedValueException('Stress-test input must contain an order-history list.');
        }

        $execution = $backtest['execution_config'] ?? [];
        if (! is_array($execution)) {
            throw new UnexpectedValueException('Stress-test execution configuration must be a record.');
        }
        $pips = $execution['slippage_pips'] ?? 0.0;
        if (! is_numeric($pips) || ! is_finite((float) $pips) || (float) $pips < 0) {
            throw new UnexpectedValueException('Stress-test slippage pips must be a finite non-negative number.');
        }
        $pipSize = $execution['slippage_pip_size'] ?? null;
        if (! is_numeric($pipSize) || (float) $pipSize <= 0 || ! is_finite((float) $pipSize)) {
            $pipSize = null;
        }

        $pair = $backtest['pair'] ?? null;
        if ($pair !== null && ! is_string($pair)) {
            throw new UnexpectedValueException('Stress-test backtest pair must be a string when present.');
        }
        if (is_string($pair) && str_contains($pair, '/') && ! preg_match('#^[^/]+/([^/]+)$#', $pair, $matches)) {
            throw new UnexpectedValueException('Stress-test backtest pair must contain one base/quote separator.');
        }
        $quote = isset($matches[1]) ? Option::some($matches[1]) : Option::none();

        return new self(
            initialCapital: (float) $initialCapital,
            trades: Vec::fromArray($orders)->filter(
                static function (mixed $order): bool {
                    if (! is_array($order)) {
                        throw new UnexpectedValueException('Every stress-test order must be a record.');
                    }

                    $entryRejected = $order['portfolio_entry_rejected'] ?? false;
                    if (! is_bool($entryRejected)) {
                        throw new UnexpectedValueException('Stress-test portfolio entry-rejected state must be boolean.');
                    }

                    return ! $entryRejected;
                },
            )->map(
                static function (mixed $order): StressTestTrade {
                    if (! is_array($order)) {
                        throw new UnexpectedValueException('Every stress-test order must be a record.');
                    }

                    return StressTestTrade::fromPersistedOrder($order);
                },
            ),
            slippage: new FixedSlippageConfig(
                enabled: filter_var($execution['slippage_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                pips: (float) $pips,
                pipSize: $pipSize === null ? null : (float) $pipSize,
            ),
            quote: $quote,
        );
    }
}
