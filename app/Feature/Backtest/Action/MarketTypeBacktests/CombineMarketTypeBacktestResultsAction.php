<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action\MarketTypeBacktests;

use App\Feature\Backtest\Config\TradeCostConfig;
use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use App\Feature\Backtest\Data\MarketTypeBacktests\PortfolioManagedOrder;
use App\Feature\Backtest\Model\MarketTypeBacktestPairRun;
use App\Feature\Backtest\Model\MarketTypeBacktestRun;
use App\Feature\Backtest\Process\MarketTypeBacktests\PortfolioCapitalSynchronizationProcess;
use Exact\Collection\Map;
use Exact\Collection\Vec;
use Exact\Query\Query;
use InvalidArgumentException;
use UnexpectedValueException;

class CombineMarketTypeBacktestResultsAction
{
    public function __construct(
        private readonly PortfolioCapitalSynchronizationProcess $portfolioCapital,
    ) {}

    /**
     * @param  Vec<MarketTypeBacktestPairRun>  $pairRuns
     * @return array<string, mixed>
     */
    public function execute(MarketTypeBacktestRun $run, Vec $pairRuns): array
    {
        if ($pairRuns->isEmpty()) {
            throw new InvalidArgumentException('At least one successful pair result is required for aggregation.');
        }

        $results = Query::from($pairRuns)->flatMap(
            static fn (MarketTypeBacktestPairRun $pairRun): Vec => Vec::fromArray($pairRun->result['results'])
                ->map(static function (array $decision) use ($pairRun): array {
                    $decision['pair'] = $pairRun->pair_name;

                    return $decision;
                }),
        )->sortBy(static fn (array $decision): array => [
            $decision['candle']['price_date'],
            $decision['pair'],
        ])->execute();
        $orders = Query::from($pairRuns)->flatMap(
            static fn (MarketTypeBacktestPairRun $pairRun): Vec => Vec::fromArray($pairRun->result['order_history'])
                ->map(static function (array $order) use ($pairRun): array {
                    $order['pair'] = $pairRun->pair_name;

                    return $order;
                }),
        )->sortBy(static fn (array $order): array => [
            $order['close_date'] ?? '9999-12-31 23:59:59',
            $order['pair'],
            $order['id'],
        ])->execute();

        $capitalMode = $run->configuration['capital_mode'] ?? 'separate';
        $pairInitialCapitals = $pairRuns->map(
            static fn (MarketTypeBacktestPairRun $pairRun): float => (float) (
                $pairRun->result['initial_capital'] ?? $run->configuration['initial_capital']
            ),
        );
        $pairEndingCapitals = $pairRuns->map(
            static fn (MarketTypeBacktestPairRun $pairRun): float => (float) (
                $pairRun->result['ending_capital'] ?? $pairRun->result['initial_capital'] ?? $run->configuration['initial_capital']
            ),
        );
        $averageInitialCapital = Query::from($pairInitialCapitals)->average()->getOrElse(0.0);
        $averageEndingCapital = Query::from($pairEndingCapitals)->average()->getOrElse(0.0);

        if (! in_array($capitalMode, ['shared', 'separate'], true)) {
            throw new UnexpectedValueException('The market-type run has an unsupported capital mode.');
        }

        if ($capitalMode === 'shared') {
            $initialCapital = (float) $run->configuration['initial_capital'];
        } else {
            $initialCapital = Query::from($pairInitialCapitals)->sum();
        }

        $managedPortfolioOrders = $orders->enumerate()->map(
            static fn (array $entry): PortfolioManagedOrder => PortfolioManagedOrder::fromPersistedArray($entry[1], $entry[0]),
        );
        $managedPortfolio = $capitalMode === 'shared'
            ? $this->portfolioCapital->execute(
                $initialCapital,
                $managedPortfolioOrders,
                UnfilledOrderPolicy::from($run->configuration['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value),
                $this->perOrderCostComponents($run->configuration['trade_costs'] ?? []),
            )
            : $this->portfolioCapital->applySeparateCapitalTimeline($initialCapital, $managedPortfolioOrders);
        $orders = $managedPortfolio->orders
            ->map(static fn (PortfolioManagedOrder $order): array => $order->toArray());
        $capital = $managedPortfolio->endingCapital;

        return [
            'pair' => 'Overall · '.$run->market_type_name,
            'timeframe' => $run->configuration['timeframe_name'],
            'from' => $run->configuration['from'],
            'to' => $run->configuration['to'],
            'initial_capital' => $initialCapital,
            'capital_mode' => $capitalMode,
            'successful_pair_count' => $pairRuns->count(),
            'average_initial_capital_per_pair' => $averageInitialCapital,
            'average_ending_capital_per_pair' => $averageEndingCapital,
            'execution_config' => [
                'slippage_enabled' => filter_var($run->configuration['slippage_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'slippage_pips' => (float) ($run->configuration['slippage_pips'] ?? 0),
                'slippage_pip_size' => isset($run->configuration['slippage_pip_size'])
                    ? (float) $run->configuration['slippage_pip_size']
                    : null,
                'partial_fill_enabled' => filter_var($run->configuration['partial_fill_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'partial_fill_ratio' => (float) ($run->configuration['partial_fill_ratio'] ?? 1),
                'unfilled_order_policy' => $run->configuration['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value,
                'trade_costs' => TradeCostConfig::fromArray($run->configuration['trade_costs'] ?? [])->toArray(),
            ],
            'ending_capital' => $capital,
            'results' => $results->toArray(),
            'order_history' => $orders->toArray(),
        ];
    }

    /** @return list<string> */
    private function perOrderCostComponents(mixed $costConfiguration): array
    {
        if (! is_array($costConfiguration)) {
            return [];
        }
        $components = $costConfiguration['components'] ?? $costConfiguration;
        if (! is_array($components)) {
            return [];
        }

        return Query::from(Map::fromArray($components)->entries())
            ->filter(
                static fn (array $entry): bool => is_string($entry[0])
                    && is_array($entry[1])
                    && filter_var($entry[1]['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    && ($entry[1]['method'] ?? null) === 'per_order',
            )
            ->select(static fn (array $entry): string => $entry[0])
            ->execute()
            ->toArray();
    }
}
