<?php

namespace Tests\Unit\Engine\Domain;

use App\Feature\Backtest\Action\RunSpecificPairBacktestAction;
use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Config\TradeCostComponentConfig;
use App\Feature\Backtest\Config\TradeCostConfig;
use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use App\Feature\Backtest\Data\BacktestOrderHistoryEntry;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\Backtest\Process\TradeCostCalculator;
use App\Feature\MarketPairs\Data\MarketPairMarketData;
use App\Feature\MarketPairs\Data\MarketPairQuantityUnit;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Platform\Strategy\Enums\StrategySignal;
use App\Platform\Strategy\Models\StrategyDecision;
use App\Platform\Strategy\Models\StrategyExecutionContext;
use App\Platform\Strategy\Strategy;
use DateTimeImmutable;
use DateTimeInterface;
use Exact\Collection\Vec;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class SpecificPairBacktestDomainTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_trade_costs_are_applied_to_entry_exit_and_holding_events(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'units']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['open' => 100, 'ask_open' => 100]),
            $this->candle('2026-10-10 09:00:00', [
                'open' => 110,
                'high' => 115,
                'low' => 105,
                'close' => 110,
                'bid_close' => 110,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->twice()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->twice()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 90, 120, 10),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));

        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'transaction_value_percent', 1),
                    'exchange_fee' => new TradeCostComponentConfig(true, 'per_order', 2),
                    'financing_fee' => new TradeCostComponentConfig(true, 'daily_notional_percent', 1),
                ],
            ),
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertEqualsWithDelta(100.0, $order->grossRealizedProfitLoss, 0.000001);
        $this->assertEqualsWithDelta(65.0, $order->realizedProfitLoss, 0.000001);
        $this->assertEqualsWithDelta(10065.0, $results->endingCapital, 0.000001);
        $this->assertEqualsWithDelta(21.0, $order->tradeCostTotals['commission'], 0.000001);
        $this->assertEqualsWithDelta(4.0, $order->tradeCostTotals['exchange_fee'], 0.000001);
        $this->assertEqualsWithDelta(10.0, $order->tradeCostTotals['financing_fee'], 0.000001);
        $this->assertEqualsWithDelta(77.0, $order->exitFills->at(0)->profitLoss, 0.000001);
        $this->assertEqualsWithDelta(12.0, array_sum($order->entryFills->at(0)->tradeCosts), 0.000001);

        $disabledConfig = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            tradeCosts: new TradeCostConfig(enabled: false, components: $config->tradeCosts->components()),
        );
        $disabledOrder = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($disabledConfig)->orders->at(0);
        $this->assertEqualsWithDelta(100.0, $disabledOrder->realizedProfitLoss, 0.000001);
        $this->assertSame(0.0, array_sum($disabledOrder->tradeCostTotals));
    }

    public function test_small_percent_fees_scale_to_large_transaction_values(): void
    {
        $cost = (new TradeCostCalculator)->transaction(
            new TradeCostComponentConfig(true, 'transaction_value_percent', 0.001),
            quantity: 1.0,
            transactionValue: 1_234_567_890_123.45,
            chargePerOrder: true,
        );

        $this->assertSame(12_345_678.9012345, $cost);
    }

    public function test_repeated_fractional_costs_accumulate_without_binary_drift(): void
    {
        $order = new BacktestOrderHistoryEntry(
            id: 1,
            signal: 'long',
            openDate: '2026-10-09 09:00:00',
            entryPrice: 10.0,
            entryPriceSource: 'ask_open',
            stopLoss: 9.0,
            takeProfit: 12.0,
            strategyQuantity: 100,
            quantityUnit: 'units',
            quantityMultiplier: 1.0,
            pnlMultiplier: 1.0,
            requestedRiskAmount: 100.0,
            positionSize: 0.0,
            requestedPositionSize: 100.0,
            capitalAtOpen: 10_000.0,
        );

        for ($fill = 0; $fill < 100; $fill++) {
            $order->recordEntryFill(1.0, 10.0, '2026-10-09 09:00:00', 'ask_open');
            $order->recordEntryTradeCosts(['commission' => 0.1]);
        }

        $this->assertSame(10.0, $order->tradeCostTotals['commission']);
        $this->assertSame(-10.0, $order->realizedProfitLoss);
    }

    public function test_fractional_exit_fees_do_not_turn_a_break_even_trade_into_a_loss(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'units']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['open' => 10.0, 'ask_open' => 10.0]),
            $this->candle('2026-10-09 10:00:00', [
                'open' => 10.3,
                'high' => 10.3,
                'low' => 10.3,
                'close' => 10.3,
                'bid_close' => 10.3,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 9.0, 12.0, 1),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'ABC',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'per_order', 0.1, 'exit'),
                    'exchange_fee' => new TradeCostComponentConfig(true, 'per_order', 0.2, 'exit'),
                ],
            ),
        );

        $result = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config);

        $this->assertSame(0.3, $result->orders->at(0)->grossRealizedProfitLoss);
        $this->assertSame(0.1, $result->orders->at(0)->tradeCostTotals['commission']);
        $this->assertSame(0.2, $result->orders->at(0)->tradeCostTotals['exchange_fee']);
        $this->assertSame(0.0, $result->orders->at(0)->profitLoss);
        $this->assertSame(10_000.0, $result->endingCapital);
    }

    public function test_borrow_fees_are_charged_for_short_positions_only(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'units']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['open' => 100, 'bid_open' => 100]),
            $this->candle('2026-10-10 09:00:00', [
                'open' => 90,
                'high' => 95,
                'low' => 85,
                'close' => 90,
                'ask_close' => 90,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 110, 80, 10),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'ABC',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'borrow_fee' => new TradeCostComponentConfig(true, 'daily_notional_percent', 1),
                ],
            ),
        );

        $order = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config)->orders->at(0);

        $this->assertEqualsWithDelta(100.0, $order->grossRealizedProfitLoss, 0.000001);
        $this->assertEqualsWithDelta(10.0, $order->tradeCostTotals['borrow_fee'], 0.000001);
        $this->assertEqualsWithDelta(90.0, $order->realizedProfitLoss, 0.000001);
    }

    public function test_per_quantity_and_transaction_value_fees_use_contract_size_without_currency_conversion(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'contract', 'contract_size' => '10.00000000']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['open' => 100, 'ask_open' => 100]),
            $this->candle('2026-10-09 10:00:00', [
                'open' => 110,
                'high' => 115,
                'low' => 105,
                'close' => 110,
                'bid_close' => 110,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 90, 120, 2),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'ABC',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'per_quantity', 0.5),
                    'exchange_fee' => new TradeCostComponentConfig(true, 'transaction_value_percent', 0.1),
                    'transaction_tax' => new TradeCostComponentConfig(true, 'transaction_value_percent', 1, 'exit'),
                ],
            ),
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertEqualsWithDelta(200.0, $order->grossRealizedProfitLoss, 0.000001);
        $this->assertEqualsWithDelta(2.0, $order->tradeCostTotals['commission'], 0.000001);
        $this->assertEqualsWithDelta(4.2, $order->tradeCostTotals['exchange_fee'], 0.000001);
        $this->assertEqualsWithDelta(22.0, $order->tradeCostTotals['transaction_tax'], 0.000001);
        $this->assertEqualsWithDelta(171.8, $order->realizedProfitLoss, 0.000001);
    }

    public function test_manual_contract_size_calculates_position_risk_and_pnl_from_filled_quantity(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'lot', 'contract_size' => '100000.00000000']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['ask_open' => '1.1000']),
            $this->candle('2026-10-09 10:00:00', [
                'bid_open' => '1.1020',
                'bid_close' => '1.1020',
                'bid_low' => '1.1010',
                'bid_high' => '1.1030',
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.09, 1.2, '2.5'),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertSame(250000.0, $order->requestedPositionSize);
        $this->assertSame(250000.0, $order->positionSize);
        $this->assertSame('2.5', $order->strategyQuantity);
        $this->assertSame('lot', $order->quantityUnit);
        $this->assertSame(2500.0, $order->riskAmount);
        $this->assertSame(500.0, $order->profitLoss);
        $this->assertSame(10500.0, $results->endingCapital);
    }

    public function test_strategy_receives_updated_capital_before_sizing_the_next_entry(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'units']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', [
                'open' => 1.0,
                'high' => 1.05,
                'low' => 0.95,
                'close' => 1.0,
            ]),
            $this->candle('2026-10-09 10:00:00', [
                'open' => 1.1,
                'high' => 1.2,
                'low' => 1.05,
                'close' => 1.15,
            ]),
            $this->candle('2026-10-09 11:00:00', [
                'open' => 1.15,
                'high' => 1.16,
                'low' => 1.14,
                'close' => 1.15,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturnUsing(
            function (iterable $candles, StrategyExecutionContext $context): \Generator {
                return (static function () use ($context): \Generator {
                    yield new StrategyDecision(StrategySignal::Long, 0.9, 1.2, 1000.0);
                    yield new StrategyDecision(StrategySignal::None, null, null);
                    yield new StrategyDecision(
                        StrategySignal::Long,
                        1.05,
                        1.4,
                        $context->currentCapital * 0.01 / 0.1,
                    );
                })();
            },
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute(new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        ));

        $firstOrder = $results->orders->at(0);
        $secondOrder = $results->orders->at(1);
        $this->assertSame(10200.0, $firstOrder->capitalAfterClose);
        $this->assertSame(1020.0, $secondOrder->strategyQuantity);
        $this->assertSame(102.0, $secondOrder->requestedRiskAmount);
    }

    public function test_entry_signal_without_positive_quantity_is_rejected(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00'),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2),
        ]));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('An entry signal must include a positive quantity.');

        (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute(new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        ));
    }

    public function test_unit_quantity_is_used_directly_without_lot_multiplier(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'units', 'contract_size' => '100000.00000000']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00'),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, '2.5'),
        ]));

        $order = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute(new SpecificPairTestConfig(
                'EUR',
                'USD',
                new DateTimeImmutable('2026-10-09 00:00:00'),
                new DateTimeImmutable('2026-10-09 23:59:59'),
                false,
                false,
            ))->orders->at(0);

        $this->assertSame(2.5, $order->requestedPositionSize);
        $this->assertSame('units', $order->quantityUnit);
        $this->assertSame(1.0, $order->quantityMultiplier);
    }

    public function test_futures_contract_multiplier_scales_risk_and_profit_without_changing_contract_quantity(): void
    {
        $pair = new MarketPair(['quantity_unit' => 'contract', 'contract_size' => '50.00000000']);
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['ask_open' => 5000]),
            $this->candle('2026-10-09 10:00:00', [
                'bid_open' => 5010,
                'bid_close' => 5010,
                'bid_low' => 5005,
                'bid_high' => 5015,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 4900, 5200, 2),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute(new SpecificPairTestConfig(
            'ES',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        ));
        $order = $results->orders->at(0);

        $this->assertSame(2.0, $order->requestedPositionSize);
        $this->assertSame(2.0, $order->positionSize);
        $this->assertSame('contract', $order->quantityUnit);
        $this->assertSame(50.0, $order->quantityMultiplier);
        $this->assertSame(50.0, $order->pnlMultiplier);
        $this->assertSame(10000.0, $order->riskAmount);
        $this->assertSame(1000.0, $order->profitLoss);
        $this->assertSame(11000.0, $results->endingCapital);
    }

    public function test_it_skips_inactive_candles_and_returns_only_active_decisions(): void
    {
        $candles = new EloquentCollection([
            $this->candle('2026-10-09 08:59:00'),
            $this->candle('2026-10-09 09:00:00'),
            $this->candle('2026-10-09 17:01:00'),
            $this->candle('2026-10-10 10:00:00'),
            $this->candle('2026-10-12 10:00:00'),
        ]);

        $pair = new MarketPair;
        $pair->setRelation('prices', $candles);

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')
            ->once()
            ->with(
                'EUR',
                'USD',
                Mockery::on(
                    fn (DateTimeInterface $date): bool => $date == new DateTimeImmutable('2026-10-09 00:00:00')
                ),
                Mockery::on(
                    fn (DateTimeInterface $date): bool => $date == new DateTimeImmutable('2026-10-12 23:59:59')
                ),
                null,
                null,
            )
            ->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-12 23:59:59'),
            '09:00',
            '17:00',
            [6, 7],
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);

        $this->assertSame(2, $results->decisions->count());
        $this->assertSame('2026-10-09 09:00:00', $results->decisions->at(0)->candle->price_date->format('Y-m-d H:i:s'));
        $this->assertFalse($results->decisions->at(0)->entered);
        $this->assertTrue($results->orders->isEmpty());
        $this->assertSame('2026-10-12 10:00:00', $results->decisions->at(1)->candle->price_date->format('Y-m-d H:i:s'));
    }

    public function test_false_active_hours_allow_full_day_except_configured_days(): void
    {
        $candles = new EloquentCollection([
            $this->candle('2026-10-09 02:00:00'),
            $this->candle('2026-10-10 02:00:00'),
        ]);
        $pair = new MarketPair;
        $pair->setRelation('prices', $candles);

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            [6],
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);

        $this->assertSame(1, $results->decisions->count());
        $this->assertSame('2026-10-09 02:00:00', $results->decisions->at(0)->candle->price_date->format('Y-m-d H:i:s'));
    }

    public function test_it_tracks_the_price_closest_to_stop_loss_and_closes_orders_outside_active_hours(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', [
                'ask_open' => 1.101,
                'bid_low' => 1.100,
                'bid_high' => 1.110,
            ]),
            $this->candle('2026-10-09 17:30:00', [
                'bid_close' => 1.096,
                'bid_low' => 1.095,
                'bid_high' => 1.105,
            ]),
            $this->candle('2026-10-09 18:00:00', [
                'bid_close' => 1.0905,
                'bid_low' => 1.089,
                'bid_high' => 1.100,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12, 1),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertSame(1, $results->decisions->count());
        $this->assertSame('2026-10-09 09:00:00', $order->openDate);
        $this->assertSame('2026-10-09 18:00:00', $order->closeDate);
        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.101, $order->entryPrice);
        $this->assertSame(1.09, $order->closePrice);
        $this->assertSame([
            'price' => 1.0905,
            'date' => '2026-10-09 18:00:00',
            'source' => 'bid_close',
            'distance' => 0.0005,
        ], $order->closestToStopLoss->getOrElseThrow(new \LogicException)->toArray());
    }

    public function test_it_uses_generic_ohlc_and_close_when_bid_quotes_are_unavailable(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['ask_open' => null]),
            $this->candle('2026-10-09 10:00:00', [
                'low' => 1.089,
                'close' => 1.088,
                'bid_low' => null,
                'bid_high' => null,
                'bid_close' => null,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12, 1),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.1, $order->entryPrice);
        $this->assertSame('open', $order->entryPriceSource);
        $this->assertSame(1.09, $order->closePrice);
        $this->assertSame(
            'low',
            $order->closestToStopLoss->getOrElseThrow(new \LogicException)->source->value,
        );
    }

    public function test_it_uses_generic_ohlc_and_close_when_ask_quotes_are_unavailable(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid_open' => null]),
            $this->candle('2026-10-09 10:00:00', [
                'high' => 1.111,
                'close' => 1.112,
                'ask_high' => null,
                'ask_low' => null,
                'ask_close' => null,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 1.11, 1.08, '9090.909090909091'),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame(1.11, $order->closePrice);
        $this->assertSame(
            'high',
            $order->closestToStopLoss->getOrElseThrow(new \LogicException)->source->value,
        );
    }

    public function test_it_closes_short_orders_at_take_profit_and_honors_close_signals(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid_open' => 1.099]),
            $this->candle('2026-10-09 10:00:00', [
                'ask_close' => 1.0805,
                'ask_high' => 1.095,
                'ask_low' => 1.079,
            ]),
            $this->candle('2026-10-09 11:00:00', ['ask_open' => 1.101]),
            $this->candle('2026-10-09 12:00:00', [
                'bid_close' => 1.105,
                'bid_low' => 1.100,
                'bid_high' => 1.110,
                'ask_close' => 1.105,
            ]),
        ]));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));

        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 1.11, 1.08, '9090.909090909091'),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::Long, 1.09, 1.12, '9090.909090909091'),
            new StrategyDecision(StrategySignal::Close, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            '09:00',
            '17:00',
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);

        $this->assertSame(2, $results->orders->count());
        $firstOrder = $results->orders->at(0);
        $secondOrder = $results->orders->at(1);
        $this->assertSame('take_profit', $firstOrder->closeReason);
        $this->assertSame('2026-10-09 10:00:00', $firstOrder->closeDate);
        $this->assertSame([
            'price' => 1.0805,
            'date' => '2026-10-09 10:00:00',
            'source' => 'ask_close',
            'distance' => 0.0005,
        ], $firstOrder->closestToTakeProfit->getOrElseThrow(new \LogicException)->toArray());
        $this->assertEqualsWithDelta(100.0, $firstOrder->riskAmount, 0.000001);
        $this->assertEqualsWithDelta(100 / 0.011, $firstOrder->positionSize, 0.000001);
        $this->assertSame(1.08, $firstOrder->closePrice);
        $this->assertEqualsWithDelta(172.7272727, $firstOrder->profitLoss, 0.000001);
        $this->assertEqualsWithDelta(10172.7272727, $firstOrder->capitalAfterClose, 0.000001);
        $this->assertSame('close', $secondOrder->closeReason);
        $this->assertSame('2026-10-09 12:00:00', $secondOrder->closeDate);
        $this->assertSame(1.1, $secondOrder->closePrice);
        $this->assertSame('bid_open', $secondOrder->exitFills->at(0)->source);
        $this->assertEqualsWithDelta($firstOrder->riskAmount, $secondOrder->riskAmount, 0.000001);
        $this->assertEqualsWithDelta(
            $firstOrder->capitalAfterClose + $secondOrder->profitLoss,
            $results->endingCapital,
            0.000001,
        );
    }

    public function test_it_applies_adverse_slippage_to_entry_and_exit_without_reapplying_spread(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', [
                'ask_open' => 1.1,
                'bid_open' => 1.1,
            ]),
            $this->candle('2026-10-09 10:00:00', [
                'bid_low' => 0.99,
                'bid_close' => 1.05,
                'low' => 0.99,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, 1000),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            slippage: new FixedSlippageConfig(enabled: true, pips: 1),
        );

        $order = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config)->orders->at(0);

        $this->assertEqualsWithDelta(1.1001, $order->entryPrice, 0.0000001);
        $this->assertEqualsWithDelta(0.9999, $order->closePrice, 0.0000001);
        $this->assertSame('ask_open', $order->entryPriceSource);
        $this->assertSame('stop_loss', $order->closeReason);
    }

    public function test_it_reverses_slippage_sides_for_short_entry_and_exit(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['bid_open' => 1.1]),
            $this->candle('2026-10-09 10:00:00', [
                'ask_high' => 1.21,
                'ask_close' => 1.15,
                'high' => 1.21,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Short, 1.2, 1.0, 1),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            slippage: new FixedSlippageConfig(enabled: true, pips: 1),
        );

        $order = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config)->orders->at(0);

        $this->assertEqualsWithDelta(1.0999, $order->entryPrice, 0.0000001);
        $this->assertEqualsWithDelta(1.2001, $order->closePrice, 0.0000001);
        $this->assertSame('stop_loss', $order->closeReason);
    }

    public function test_it_retries_entry_and_exit_fills_and_charges_per_order_commission_once_per_side(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00', ['ask_open' => 1.1]),
            $this->candle('2026-10-09 10:00:00', [
                'ask_open' => 1.12,
                'high' => 1.15,
                'low' => 1.11,
                'bid_high' => 1.15,
                'bid_low' => 1.11,
            ]),
            $this->candle('2026-10-09 11:00:00', [
                'bid_close' => 1.01,
                'bid_low' => 0.99,
                'bid_high' => 1.15,
            ]),
            $this->candle('2026-10-09 12:00:00', [
                'bid_close' => 1.02,
                'bid_low' => 1.005,
                'bid_high' => 1.15,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, 1000),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            partialFill: new PartialFillConfig(enabled: true, ratio: 0.5),
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'per_order', 2),
                ],
            ),
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertEqualsWithDelta(1000, $order->requestedPositionSize, 0.000001);
        $this->assertEqualsWithDelta(1000, $order->positionSize, 0.000001);
        $this->assertEqualsWithDelta(1.11, $order->entryPrice, 0.000001);
        $this->assertEqualsWithDelta(110, $order->riskAmount, 0.000001);
        $this->assertEqualsWithDelta(100, $order->requestedRiskAmount, 0.000001);
        $this->assertEqualsWithDelta(1000, $order->closedPositionSize, 0.000001);
        $this->assertEqualsWithDelta(1.0, $order->closePrice, 0.000001);
        $this->assertEqualsWithDelta(-114, $order->profitLoss, 0.000001);
        $this->assertEqualsWithDelta(9886, $results->endingCapital, 0.000001);
        $this->assertEqualsWithDelta(4, $order->tradeCostTotals['commission'], 0.000001);
        $this->assertCount(2, $order->entryFills);
        $this->assertCount(2, $order->exitFills);
    }

    public function test_cancel_policy_records_unfilled_entry_and_exit_quantities(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00'),
            $this->candle('2026-10-09 10:00:00', [
                'bid_close' => 1.05,
                'bid_low' => 0.99,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, 1000),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            partialFill: new PartialFillConfig(enabled: true, ratio: 0.5, unfilledOrderPolicy: UnfilledOrderPolicy::Cancel),
        );

        $order = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config)->orders->at(0);

        $this->assertEqualsWithDelta(500, $order->positionSize, 0.000001);
        $this->assertEqualsWithDelta(500, $order->cancelledEntryQuantity, 0.000001);
        $this->assertEqualsWithDelta(250, $order->closedPositionSize, 0.000001);
        $this->assertEqualsWithDelta(250, $order->remainingPositionSize, 0.000001);
        $this->assertEqualsWithDelta(250, $order->cancelledExitQuantity, 0.000001);
        $this->assertNull($order->closeDate);
    }

    public function test_keep_policy_does_not_fill_entry_remainder_after_history_ends(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00'),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, 1000),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            partialFill: new PartialFillConfig(enabled: true, ratio: 0.5),
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertEqualsWithDelta(500, $order->positionSize, 0.000001);
        $this->assertEqualsWithDelta(500, $order->remainingEntryQuantity, 0.000001);
        $this->assertTrue($order->exitFills->isEmpty());
        $this->assertNull($order->closeDate);
        $this->assertSame(10000.0, $results->endingCapital);
    }

    public function test_keep_policy_books_only_filled_exit_quantity_when_history_ends(): void
    {
        $pair = new MarketPair;
        $pair->setRelation('prices', new EloquentCollection([
            $this->candle('2026-10-09 09:00:00'),
            $this->candle('2026-10-09 10:00:00', [
                'bid_close' => 1.05,
                'bid_low' => 0.99,
            ]),
        ]));
        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray([
            new StrategyDecision(StrategySignal::Long, 1.0, 1.2, 1000),
            new StrategyDecision(StrategySignal::None, null, null),
        ]));
        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-09 23:59:59'),
            false,
            false,
            partialFill: new PartialFillConfig(enabled: true, ratio: 0.5),
        );

        $results = (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))->execute($config);
        $order = $results->orders->at(0);

        $this->assertSame([0.5, 0.5, 'keep'], [
            $config->partialFill->ratio,
            $order->exitFills->at(0)->quantity / $order->positionSize,
            $config->partialFill->unfilledOrderPolicy->value,
        ]);
        $this->assertEqualsWithDelta(500, $order->closedPositionSize, 0.000001);
        $this->assertEqualsWithDelta(500, $order->remainingPositionSize, 0.000001);
        $this->assertEqualsWithDelta(-50, $order->realizedProfitLoss, 0.000001);
        $this->assertNull($order->profitLoss);
        $this->assertEqualsWithDelta(9950, $results->endingCapital, 0.000001);
    }

    public function test_buy_limit_waits_for_a_later_candle_and_fills_at_the_limit(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100, 'ask_low' => 98]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'ask_low' => 98, 'ask_high' => 102]),
                $this->candle('2026-10-09 11:00:00', ['open' => 100, 'close' => 100]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 95, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::None, null, null),
                new StrategyDecision(StrategySignal::Close, null, null),
            ],
        );

        $order = $result->orders->at(0);
        $this->assertSame('2026-10-09 10:00:00', $order->openDate);
        $this->assertSame(99.0, $order->entryPrice);
        $this->assertSame('ask_low', $order->entryPriceSource);
        $this->assertSame(99.0, $order->requestedEntryPrice);
        $this->assertSame('filled', $result->decisions->at(0)->entryOrderStatus);
        $this->assertSame(99.0, $result->decisions->at(0)->entryLimitPrice);
    }

    public function test_sell_limit_fills_when_bid_high_reaches_its_limit(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'bid_high' => 102]),
            ],
            [
                new StrategyDecision(StrategySignal::Short, 105, 90, 1, limitPrice: 101),
                new StrategyDecision(StrategySignal::None, null, null),
            ],
        );

        $order = $result->orders->at(0);
        $this->assertSame(StrategySignal::Short->value, $order->signal);
        $this->assertSame(101.0, $order->entryPrice);
        $this->assertSame('bid_high', $order->entryPriceSource);
        $this->assertSame('filled', $result->decisions->at(0)->entryOrderStatus);
    }

    public function test_unfilled_limit_remains_pending_and_does_not_create_a_position(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100, 'ask_low' => 98]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'ask_low' => 99.5]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 95, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::None, null, null),
            ],
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'per_order', 5),
                ],
            ),
        );

        $this->assertTrue($result->orders->isEmpty());
        $this->assertSame('pending', $result->decisions->at(0)->entryOrderStatus);
        $this->assertSame(10000.0, $result->endingCapital);
    }

    public function test_limit_gap_fill_respects_the_limit_after_slippage(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100]),
                $this->candle('2026-10-09 10:00:00', ['open' => 97, 'ask_open' => 97, 'ask_low' => 95]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 90, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::None, null, null),
            ],
            slippage: new FixedSlippageConfig(enabled: true, pips: 3, pipSize: 1),
        );

        $order = $result->orders->at(0);
        $this->assertSame(99.0, $order->entryPrice);
        $this->assertSame('ask_open', $order->entryPriceSource);
    }

    public function test_exit_signal_cancels_a_limit_that_has_not_filled(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'ask_low' => 99.5]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 95, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::Close, null, null),
            ],
        );

        $this->assertTrue($result->orders->isEmpty());
        $this->assertSame('cancelled', $result->decisions->at(0)->entryOrderStatus);
    }

    public function test_partial_limit_fill_charges_fees_only_on_filled_quantity_and_does_not_duplicate(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'ask_low' => 98]),
                $this->candle('2026-10-09 11:00:00', ['open' => 100, 'ask_low' => 99.5]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 95, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::None, null, null),
                new StrategyDecision(StrategySignal::None, null, null),
            ],
            tradeCosts: new TradeCostConfig(
                enabled: true,
                components: [
                    'commission' => new TradeCostComponentConfig(true, 'per_quantity', 0.1),
                ],
            ),
            partialFill: new PartialFillConfig(enabled: true, ratio: 0.5),
        );

        $this->assertSame(1, $result->orders->count());
        $this->assertSame(0.5, $result->orders->at(0)->positionSize);
        $this->assertSame(0.5, $result->orders->at(0)->remainingEntryQuantity);
        $this->assertSame(0.05, $result->orders->at(0)->tradeCostTotals['commission']);
        $this->assertSame('partially_filled', $result->decisions->at(0)->entryOrderStatus);
    }

    public function test_limit_filled_position_uses_existing_stop_loss_exit_logic(): void
    {
        $result = $this->runBacktest(
            [
                $this->candle('2026-10-09 09:00:00', ['open' => 100]),
                $this->candle('2026-10-09 10:00:00', ['open' => 100, 'ask_low' => 98]),
                $this->candle('2026-10-09 11:00:00', ['open' => 94, 'bid_low' => 93]),
            ],
            [
                new StrategyDecision(StrategySignal::Long, 95, 110, 1, limitPrice: 99),
                new StrategyDecision(StrategySignal::None, null, null),
                new StrategyDecision(StrategySignal::None, null, null),
            ],
        );

        $order = $result->orders->at(0);
        $this->assertSame('stop_loss', $order->closeReason);
        $this->assertSame('2026-10-09 11:00:00', $order->closeDate);
    }

    /**
     * @param  array<string, int|float|string|null>  $attributes
     */
    private function candle(string $timestamp, array $attributes = []): MarketPrice
    {
        $open = (float) ($attributes['open'] ?? 1.1);
        $close = (float) ($attributes['close'] ?? $open);
        $high = (float) ($attributes['high'] ?? $open);
        $low = (float) ($attributes['low'] ?? $open);

        return new MarketPrice([
            'price_date' => $timestamp,
            'open' => $open,
            'close' => $close,
            'high' => $high,
            'low' => $low,
            'bid_open' => $open,
            'bid_high' => $high,
            'bid_low' => $low,
            'bid_close' => $close,
            'ask_open' => $open,
            'ask_high' => $high,
            'ask_low' => $low,
            'ask_close' => $close,
            ...$attributes,
        ]);
    }

    /**
     * @param  list<MarketPrice>  $candles
     * @param  list<StrategyDecision>  $decisions
     */
    private function runBacktest(
        array $candles,
        array $decisions,
        ?TradeCostConfig $tradeCosts = null,
        ?FixedSlippageConfig $slippage = null,
        ?PartialFillConfig $partialFill = null,
    ): SpecificPairBacktestResult {
        $pair = new MarketPair(['quantity_unit' => 'units']);
        $pair->setRelation('prices', new EloquentCollection($candles));

        $repository = Mockery::mock(MarketPairRepository::class);
        $repository->shouldReceive('findWithPricesBetween')->once()->andReturn($this->marketData($pair));
        $strategy = Mockery::mock(Strategy::class);
        $strategy->shouldReceive('execute')->once()->andReturn(Vec::fromArray($decisions));

        $config = new SpecificPairTestConfig(
            'EUR',
            'USD',
            new DateTimeImmutable('2026-10-09 00:00:00'),
            new DateTimeImmutable('2026-10-10 23:59:59'),
            false,
            false,
            slippage: $slippage ?? new FixedSlippageConfig,
            partialFill: $partialFill ?? new PartialFillConfig,
            tradeCosts: $tradeCosts ?? new TradeCostConfig,
        );

        return (new RunSpecificPairBacktestAction($repository, new SpecificPairBacktestProcess($strategy)))
            ->execute($config);
    }

    private function marketData(MarketPair $pair): MarketPairMarketData
    {
        $quantityUnit = MarketPairQuantityUnit::tryFrom($pair->quantity_unit ?? 'units');
        if ($quantityUnit === null) {
            throw new \UnexpectedValueException('Unsupported test market-pair quantity unit.');
        }

        return new MarketPairMarketData(
            candles: Vec::fromArray($pair->prices->all()),
            quantityUnit: $quantityUnit,
            contractSize: is_numeric($pair->contract_size) ? (string) $pair->contract_size : '1',
        );
    }
}
