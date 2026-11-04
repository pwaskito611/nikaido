<?php

declare(strict_types=1);

namespace App\Feature\MarketExploration\Action;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\BacktestOrderHistoryEntry;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use DateTimeImmutable;
use Exact\Query\Query;

class RunMarketExplorationStrategyAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly RunSpecificPairBacktestAction $runBacktest,
    ) {}

    /**
     * @return array{
     *     summary: array{trades: int, wins: int, losses: int, closed_trades: int},
     *     trades: list<array{
     *         signal: string,
     *         entry_fills: list<array<string, mixed>>,
     *         exit_fills: list<array<string, mixed>>
     *     }>
     * }
     */
    public function execute(int $pairId, int $timeframeTypeId, string $from, string $to): array
    {
        $pair = $this->marketPairs->findOrFail($pairId);
        $result = $this->runBacktest->execute(new SpecificPairTestConfig(
            base: $pair->base,
            quote: $pair->quote,
            from: new DateTimeImmutable($from.' 00:00:00'),
            to: new DateTimeImmutable($to.' 23:59:59'),
            activeFrom: false,
            activeUntil: false,
            timeframeTypeId: $timeframeTypeId,
            marketPairId: $pair->id,
        ));

        $filledOrders = Query::from($result->orders)
            ->select(static fn (BacktestOrderHistoryEntry $order): array => $order->jsonSerialize())
            ->filter(static fn (array $order): bool => $order['entry_filled_quantity'] > 0)
            ->execute();
        $closedOrders = Query::from($filledOrders)
            ->filter(static fn (array $order): bool => $order['exit_status'] === 'filled')
            ->execute();

        return [
            'summary' => [
                'trades' => $filledOrders->count(),
                'wins' => Query::from($closedOrders)
                    ->filter(static fn (array $order): bool => $order['realized_profit_loss'] > 0)
                    ->count(),
                'losses' => Query::from($closedOrders)
                    ->filter(static fn (array $order): bool => $order['realized_profit_loss'] < 0)
                    ->count(),
                'closed_trades' => $closedOrders->count(),
            ],
            'trades' => Query::from($filledOrders)
                ->select(static fn (array $order): array => [
                    'signal' => $order['signal'],
                    'entry_fills' => $order['entry_fills'],
                    'exit_fills' => $order['exit_fills'],
                ])
                ->execute()
                ->toArray(),
        ];
    }
}
