<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\MarketExploration;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Data\BacktestExecutionConfiguration;
use App\Feature\Backtest\Data\BacktestOrderHistoryEntry;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\MarketExploration\Action\RunMarketExplorationStrategyAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Exact\Collection\Vec;
use Mockery;
use Tests\TestCase;

class RunMarketExplorationStrategyActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_summarizes_filled_winning_losing_and_open_trades(): void
    {
        $pair = new MarketPair(['base' => 'EUR', 'quote' => 'USD']);
        $pair->setAttribute('id', 7);
        $marketPairs = Mockery::mock(MarketPairRepository::class);
        $marketPairs->shouldReceive('findOrFail')->once()->with(7)->andReturn($pair);

        $backtest = new SpecificPairBacktestResult(
            decisions: Vec::empty(),
            orders: Vec::of(
                $this->order(1, 'long', 110.0),
                $this->order(2, 'short', 110.0),
                $this->order(3, 'long', null),
                $this->order(4, 'short', null, false),
            ),
            initialCapital: 1000.0,
            quantityUnit: 'units',
            unitsPerLot: 1.0,
            quantityMultiplier: 1.0,
            endingCapital: 1000.0,
            executionConfiguration: new BacktestExecutionConfiguration(
                new FixedSlippageConfig,
                new PartialFillConfig,
            ),
        );
        $runBacktest = Mockery::mock(RunSpecificPairBacktestAction::class);
        $runBacktest->shouldReceive('execute')->once()->andReturn($backtest);

        $result = (new RunMarketExplorationStrategyAction($marketPairs, $runBacktest))
            ->execute(7, 2, '2026-10-10', '2026-10-11');

        $this->assertSame([
            'trades' => 3,
            'wins' => 1,
            'losses' => 1,
            'closed_trades' => 2,
        ], $result['summary']);
        $this->assertSame(['long', 'short', 'long'], array_column($result['trades'], 'signal'));
        $this->assertCount(1, $result['trades'][0]['entry_fills']);
        $this->assertCount(1, $result['trades'][0]['exit_fills']);
        $this->assertSame([], $result['trades'][2]['exit_fills']);
    }

    private function order(
        int $id,
        string $signal,
        ?float $closePrice,
        bool $fillEntry = true,
    ): BacktestOrderHistoryEntry {
        $order = new BacktestOrderHistoryEntry(
            id: $id,
            signal: $signal,
            openDate: '2026-10-10 10:00:00',
            entryPrice: 100.0,
            entryPriceSource: 'market',
            stopLoss: $signal === 'long' ? 90.0 : 110.0,
            takeProfit: $signal === 'long' ? 120.0 : 80.0,
            strategyQuantity: 1,
            quantityUnit: 'units',
            quantityMultiplier: 1.0,
            pnlMultiplier: 1.0,
            requestedRiskAmount: 10.0,
            positionSize: 0.0,
            requestedPositionSize: 1.0,
            capitalAtOpen: 1000.0,
        );

        if ($fillEntry) {
            $order->recordEntryFill(1.0, 100.0, '2026-10-10 10:00:00', 'market');
        }
        if ($closePrice !== null) {
            $order->recordExitFill(1.0, $closePrice, '2026-10-10 11:00:00', 'market', 'close');
        }

        return $order;
    }
}
