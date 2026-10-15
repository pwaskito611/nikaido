<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use App\Feature\Backtest\Data\StressTestInput;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class BacktestAnalysisInput
{
    /**
     * @param  Vec<BacktestAnalysisDecision>  $decisions
     * @param  Vec<BacktestAnalysisOrder>  $orders
     */
    public function __construct(
        public float $initialCapital,
        public Vec $decisions,
        public Vec $orders,
        public Option $stressTestInput,
    ) {}

    /**
     * Decode session-stored results at the controller boundary.
     *
     * @param  array<string, mixed>  $result
     */
    public static function fromPersistedResult(array $result, bool $includeStressTest = false): self
    {
        $initialCapital = $result['initial_capital'] ?? null;
        $decisions = $result['results'] ?? null;
        $orders = $result['order_history'] ?? null;
        if (! is_numeric($initialCapital) || ! is_finite((float) $initialCapital)) {
            throw new UnexpectedValueException('Backtest analysis requires finite initial capital.');
        }
        if (! is_array($decisions) || ! array_is_list($decisions)) {
            throw new UnexpectedValueException('Backtest analysis requires a decision list.');
        }
        if (! is_array($orders) || ! array_is_list($orders)) {
            throw new UnexpectedValueException('Backtest analysis requires an order-history list.');
        }

        return new self(
            initialCapital: (float) $initialCapital,
            decisions: Vec::fromArray($decisions)->map(
                static function (mixed $decision): BacktestAnalysisDecision {
                    if (! is_array($decision)) {
                        throw new UnexpectedValueException('Every backtest decision must be a record.');
                    }

                    return BacktestAnalysisDecision::fromArray($decision);
                },
            ),
            orders: Vec::fromArray($orders)->map(
                static function (mixed $order): BacktestAnalysisOrder {
                    if (! is_array($order)) {
                        throw new UnexpectedValueException('Every backtest order must be a record.');
                    }

                    return BacktestAnalysisOrder::fromArray($order);
                },
            ),
            stressTestInput: $includeStressTest
                ? Option::some(StressTestInput::fromPersistedBacktest($result))
                : Option::none(),
        );
    }
}
