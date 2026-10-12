<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypeBacktests;

use App\Engine\Models\MarketTypeBacktestPairRun;
use App\Engine\Models\MarketTypeBacktestRun;

class CombineMarketTypeBacktestResultsAction
{
    /**
     * @param  list<MarketTypeBacktestPairRun>  $pairRuns
     * @return array<string, mixed>
     */
    public function execute(MarketTypeBacktestRun $run, array $pairRuns): array
    {
        $results = [];
        $orders = [];
        foreach ($pairRuns as $pairRun) {
            $pairResult = $pairRun->result;
            foreach ($pairResult['results'] as $decision) {
                $decision['pair'] = $pairRun->pair_name;
                $results[] = $decision;
            }
            foreach ($pairResult['order_history'] as $order) {
                $order['pair'] = $pairRun->pair_name;
                $orders[] = $order;
            }
        }

        usort(
            $results,
            static fn (array $left, array $right): int => [
                $left['candle']['price_date'],
                $left['pair'],
            ] <=> [
                $right['candle']['price_date'],
                $right['pair'],
            ],
        );
        usort(
            $orders,
            static fn (array $left, array $right): int => [
                $left['close_date'] ?? '9999-12-31 23:59:59',
                $left['pair'],
                $left['id'],
            ] <=> [
                $right['close_date'] ?? '9999-12-31 23:59:59',
                $right['pair'],
                $right['id'],
            ],
        );

        $initialCapital = (float) $run->configuration['initial_capital'];
        $capital = $initialCapital;
        $exitEvents = [];
        foreach ($orders as $orderIndex => $order) {
            foreach ($order['exit_fills'] ?? [] as $fillIndex => $fill) {
                if (is_numeric($fill['profit_loss'] ?? null)) {
                    $exitEvents[] = [
                        'date' => $fill['date'],
                        'pair' => $order['pair'],
                        'order_id' => $order['id'],
                        'order_index' => $orderIndex,
                        'fill_index' => $fillIndex,
                        'profit_loss' => (float) $fill['profit_loss'],
                    ];
                }
            }
            if (($order['exit_fills'] ?? []) === [] && $order['close_date'] !== null && is_numeric($order['profit_loss'])) {
                $exitEvents[] = [
                    'date' => $order['close_date'],
                    'pair' => $order['pair'],
                    'order_id' => $order['id'],
                    'order_index' => $orderIndex,
                    'fill_index' => null,
                    'profit_loss' => (float) $order['profit_loss'],
                ];
            }
        }
        usort($exitEvents, static fn (array $left, array $right): int => [
            $left['date'],
            $left['pair'],
            $left['order_id'],
        ] <=> [
            $right['date'],
            $right['pair'],
            $right['order_id'],
        ]);
        foreach ($exitEvents as $event) {
            $capital += $event['profit_loss'];
            if ($event['fill_index'] !== null) {
                $orders[$event['order_index']]['exit_fills'][$event['fill_index']]['capital_after'] = $capital;
                $lastFillIndex = array_key_last($orders[$event['order_index']]['exit_fills']);
                if ($event['fill_index'] === $lastFillIndex
                    && $orders[$event['order_index']]['close_date'] !== null) {
                    $orders[$event['order_index']]['capital_after_close'] = $capital;
                }
            } else {
                $orders[$event['order_index']]['capital_after_close'] = $capital;
            }
        }

        return [
            'pair' => 'Overall · '.$run->market_type_name,
            'timeframe' => $run->configuration['timeframe_name'],
            'from' => $run->configuration['from'],
            'to' => $run->configuration['to'],
            'initial_capital' => $initialCapital,
            'risk_per_trade_percent' => (float) $run->configuration['risk_per_trade_percent'],
            'execution_config' => [
                'slippage_enabled' => filter_var($run->configuration['slippage_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'slippage_pips' => (float) ($run->configuration['slippage_pips'] ?? 0),
                'slippage_pip_size' => isset($run->configuration['slippage_pip_size'])
                    ? (float) $run->configuration['slippage_pip_size']
                    : null,
                'partial_fill_enabled' => filter_var($run->configuration['partial_fill_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'partial_fill_ratio' => (float) ($run->configuration['partial_fill_ratio'] ?? 1),
                'unfilled_order_policy' => $run->configuration['unfilled_order_policy'] ?? 'keep',
            ],
            'ending_capital' => $capital,
            'results' => $results,
            'order_history' => $orders,
        ];
    }
}
