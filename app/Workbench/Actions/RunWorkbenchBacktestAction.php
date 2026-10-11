<?php

declare(strict_types=1);

namespace App\Workbench\Actions;

use App\Engine\Actions\RunSpecificPairBacktestAction;
use App\Engine\Config\SpecificPairTestConfig;
use App\Engine\Models\BacktestOrderHistoryEntry;
use App\Workbench\Actions\MarketPairs\ViewMarketPairAction;
use App\Workbench\Actions\TimeframeTypes\ViewTimeframeTypeAction;
use DateTimeImmutable;

class RunWorkbenchBacktestAction
{
    public function __construct(
        private readonly ViewMarketPairAction $viewMarketPair,
        private readonly ViewTimeframeTypeAction $viewTimeframeType,
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
     *     excluded_days?: list<int|string>
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
     *     order_history: list<array<string, mixed>>
     * }
     */
    public function execute(array $validated): array
    {
        $pair = $this->viewMarketPair->execute((int) $validated['market_pair_id']);
        $timeframe = $this->viewTimeframeType->execute((int) $validated['timeframe_type_id']);

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
        ];
    }
}
