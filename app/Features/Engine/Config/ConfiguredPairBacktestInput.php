<?php

declare(strict_types=1);

namespace App\Features\Engine\Config;

use App\Features\Engine\Domain\ActiveTradingSchedule;
use DateTimeImmutable;
use Exact\Collection\Vec;
use InvalidArgumentException;

final readonly class ConfiguredPairBacktestInput
{
    /**
     * @param  Vec<int>  $excludedDays
     * @param  Option<float>  $slippagePipSize
     */
    public function __construct(
        public int $marketPairId,
        public int $timeframeTypeId,
        public float $initialCapital,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public ActiveTradingSchedule $activeSchedule,
        public Vec $excludedDays,
        public FixedSlippageConfig $slippage,
        public PartialFillConfig $partialFill,
    ) {
        if ($marketPairId < 1 || $timeframeTypeId < 1) {
            throw new InvalidArgumentException('Configured backtests require positive pair and timeframe IDs.');
        }
        if (! is_finite($initialCapital) || $initialCapital <= 0) {
            throw new InvalidArgumentException('Configured backtests require positive finite initial capital.');
        }
        if ($from > $to) {
            throw new InvalidArgumentException('The configured backtest start must not be after its end.');
        }
        foreach ($excludedDays as $day) {
            if (! is_int($day) || $day < 1 || $day > 7) {
                throw new InvalidArgumentException('Excluded days must be ISO-8601 weekday numbers from 1 to 7.');
            }
        }
    }

    /**
     * Adapt validated HTTP input or persisted run configuration into typed values.
     *
     * @param array{
     *     market_pair_id: int|string,
     *     timeframe_type_id: int|string,
     *     initial_capital: int|float|string,
     *     from: string,
     *     to: string,
     *     active_from: string|false,
     *     active_until: string|false,
     *     excluded_days?: list<int|string>,
     *     slippage_enabled?: bool|string,
     *     slippage_pips?: int|float|string|null,
     *     slippage_pip_size?: int|float|string|null,
     *     partial_fill_enabled?: bool|string,
     *     partial_fill_ratio?: int|float|string|null,
     *     unfilled_order_policy?: 'keep'|'cancel'
     * } $input
     */
    public static function fromArray(array $input): self
    {
        $days = $input['excluded_days'] ?? [];
        if (! is_array($days) || ! array_is_list($days)) {
            throw new InvalidArgumentException('Configured backtest excluded days must be a list.');
        }

        return new self(
            marketPairId: (int) $input['market_pair_id'],
            timeframeTypeId: (int) $input['timeframe_type_id'],
            initialCapital: (float) $input['initial_capital'],
            from: new DateTimeImmutable($input['from']),
            to: new DateTimeImmutable($input['to']),
            activeSchedule: ActiveTradingSchedule::fromLegacyInput(
                $input['active_from'],
                $input['active_until'],
            ),
            excludedDays: Vec::fromArray(array_map('intval', $days)),
            slippage: new FixedSlippageConfig(
                enabled: filter_var($input['slippage_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                pips: (float) ($input['slippage_pips'] ?? 0),
                pipSize: isset($input['slippage_pip_size']) ? (float) $input['slippage_pip_size'] : null,
            ),
            partialFill: new PartialFillConfig(
                enabled: filter_var($input['partial_fill_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ratio: (float) ($input['partial_fill_ratio'] ?? 1),
                unfilledOrderPolicy: UnfilledOrderPolicy::from($input['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value),
            ),
        );
    }
}
