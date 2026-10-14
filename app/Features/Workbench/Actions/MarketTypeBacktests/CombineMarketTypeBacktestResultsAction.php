<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypeBacktests;

use App\Features\Engine\Config\UnfilledOrderPolicy;
use App\Features\Engine\Models\MarketTypeBacktestPairRun;
use App\Features\Engine\Models\MarketTypeBacktestRun;
use App\Features\Workbench\Domain\MarketTypeBacktests\PortfolioCapitalSynchronizationDomain;
use App\Features\Workbench\Domain\MarketTypeBacktests\PortfolioManagedOrder;
use Exact\Collection\Vec;
use InvalidArgumentException;
use UnexpectedValueException;

class CombineMarketTypeBacktestResultsAction
{
    public function __construct(
        private readonly PortfolioCapitalSynchronizationDomain $portfolioCapital,
    ) {}

    /**
     * @param  list<MarketTypeBacktestPairRun>  $pairRuns
     * @return array<string, mixed>
     */
    public function execute(MarketTypeBacktestRun $run, array $pairRuns): array
    {
        if ($pairRuns === []) {
            throw new InvalidArgumentException('At least one successful pair result is required for aggregation.');
        }

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

        $capitalMode = $run->configuration['capital_mode'] ?? 'separate';
        $pairInitialCapitals = array_map(
            static fn (MarketTypeBacktestPairRun $pairRun): float => (float) (
                $pairRun->result['initial_capital'] ?? $run->configuration['initial_capital']
            ),
            $pairRuns,
        );
        $pairEndingCapitals = array_map(
            static fn (MarketTypeBacktestPairRun $pairRun): float => (float) (
                $pairRun->result['ending_capital'] ?? $pairRun->result['initial_capital'] ?? $run->configuration['initial_capital']
            ),
            $pairRuns,
        );
        $averageInitialCapital = array_sum($pairInitialCapitals) / count($pairInitialCapitals);
        $averageEndingCapital = array_sum($pairEndingCapitals) / count($pairEndingCapitals);

        if (! in_array($capitalMode, ['shared', 'separate'], true)) {
            throw new UnexpectedValueException('The market-type run has an unsupported capital mode.');
        }

        if ($capitalMode === 'shared') {
            $initialCapital = (float) $run->configuration['initial_capital'];
        } else {
            $initialCapital = array_sum($pairInitialCapitals);
        }

        $managedPortfolioOrders = Vec::empty();
        foreach ($orders as $index => $order) {
            $managedPortfolioOrders = $managedPortfolioOrders->append(
                PortfolioManagedOrder::fromPersistedArray($order, $index),
            );
        }
        $managedPortfolio = $capitalMode === 'shared'
            ? $this->portfolioCapital->execute(
                $initialCapital,
                $managedPortfolioOrders,
                UnfilledOrderPolicy::from($run->configuration['unfilled_order_policy'] ?? UnfilledOrderPolicy::Keep->value),
            )
            : $this->portfolioCapital->applySeparateCapitalTimeline($initialCapital, $managedPortfolioOrders);
        $orders = $managedPortfolio->orders
            ->map(static fn (PortfolioManagedOrder $order): array => $order->toArray())
            ->toArray();
        $capital = $managedPortfolio->endingCapital;

        return [
            'pair' => 'Overall · '.$run->market_type_name,
            'timeframe' => $run->configuration['timeframe_name'],
            'from' => $run->configuration['from'],
            'to' => $run->configuration['to'],
            'initial_capital' => $initialCapital,
            'capital_mode' => $capitalMode,
            'successful_pair_count' => count($pairRuns),
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
            ],
            'ending_capital' => $capital,
            'results' => $results,
            'order_history' => $orders,
        ];
    }
}
