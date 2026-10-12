<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use App\Engine\Config\FixedSlippageConfig;
use App\Engine\Config\PartialFillConfig;
use App\Engine\Config\SpecificPairTestConfig;
use App\Engine\Models\BacktestOrderHistoryEntry;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Repositories\TimeframeTypeRepository;
use DateTimeImmutable;

class RunConfiguredPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly RunSpecificPairBacktestAction $runBacktest,
    ) {}

    /**
     * @param  array{
     *     market_pair_id: int|string,
     *     timeframe_type_id: int|string,
     *     initial_capital: int|float|string,
     *     risk_per_trade_percent: int|float|string,
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
     *     unfilled_order_policy?: string
     * }  $validated
     * @return array{
     *     pair: string,
     *     timeframe: string,
     *     from: string,
     *     to: string,
     *     results: list<array{
     *         candle: array<string, mixed>,
     *         result: bool,
     *         signal: string,
     *         stop_loss: ?float,
     *         take_profit: ?float
     *     }>,
     *     order_history: list<array<string, mixed>>,
     *     execution_config: array<string, bool|float|string|null>
     * }
     */
    public function execute(array $validated): array
    {
        $pair = $this->marketPairs->findOrFail((int) $validated['market_pair_id']);
        $timeframe = $this->timeframes->findOrFail((int) $validated['timeframe_type_id']);

        $backtest = $this->runBacktest->execute(new SpecificPairTestConfig(
            base: $pair->base,
            quote: $pair->quote,
            from: new DateTimeImmutable($validated['from']),
            to: new DateTimeImmutable($validated['to']),
            activeFrom: $validated['active_from'],
            activeUntil: $validated['active_until'],
            excludedDays: array_map('intval', $validated['excluded_days'] ?? []),
            timeframeTypeId: (int) $validated['timeframe_type_id'],
            initialCapital: (float) $validated['initial_capital'],
            riskPerTradePercent: (float) $validated['risk_per_trade_percent'],
            marketPairId: $pair->id,
            slippage: new FixedSlippageConfig(
                enabled: filter_var($validated['slippage_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                pips: (float) ($validated['slippage_pips'] ?? 0),
                pipSize: isset($validated['slippage_pip_size']) ? (float) $validated['slippage_pip_size'] : null,
            ),
            partialFill: new PartialFillConfig(
                enabled: filter_var($validated['partial_fill_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ratio: (float) ($validated['partial_fill_ratio'] ?? 1),
                unfilledOrderPolicy: $validated['unfilled_order_policy'] ?? 'keep',
            ),
        ));

        return [
            'pair' => $pair->base.'/'.$pair->quote,
            'timeframe' => $timeframe->name,
            'from' => $validated['from'],
            'to' => $validated['to'],
            'initial_capital' => (float) $validated['initial_capital'],
            'risk_per_trade_percent' => (float) $validated['risk_per_trade_percent'],
            'ending_capital' => $backtest['ending_capital'],
            'results' => $backtest['results']->map(static fn (array $result): array => [
                'candle' => $result['candle']->toArray(),
                'result' => $result['result'],
                'signal' => $result['signal'],
                'stop_loss' => $result['stop_loss'],
                'take_profit' => $result['take_profit'],
            ])->toArray(),
            'order_history' => $backtest['order_history']
                ->map(static fn (BacktestOrderHistoryEntry $order): array => $order->jsonSerialize())
                ->toArray(),
            'execution_config' => $backtest['execution_config'],
        ];
    }
}
