<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Feature\Backtest\Action\InitiateMarketTypeBacktestAction;
use App\Feature\Backtest\Action\RunConfiguredPairBacktestAction;
use App\Feature\Backtest\Config\ConfiguredPairBacktestInput;
use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\PartialFillConfig;
use App\Feature\Backtest\Data\BacktestExecutionConfiguration;
use App\Feature\Backtest\Data\ConfiguredPairBacktestResult;
use App\Feature\Backtest\Action\MarketTypeBacktests\RecordMarketTypeBacktestPairResultAction as RecordMarketTypeBacktestPairResultAction;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Job\RunMarketTypeBacktestPairJob;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Feature\MarketTypes\Model\MarketType;
use App\Feature\Backtest\Model\MarketTypeBacktestRun;
use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\Backtest\Repository\MarketTypeBacktestRunRepository;
use App\Feature\MarketTypes\Repository\MarketTypeRepository;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
use Exact\Collection\Vec;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MarketTypeBacktestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    public function test_market_type_endpoint_queues_jobs_only_for_pairs_with_matching_prices(): void
    {
        Bus::fake();
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $otherTimeframe = TimeframeType::create(['name' => '4h']);
        $eligibleOne = $this->createPairWithPrice($type, 'EUR', 'USD', $timeframe, '2026-10-10 10:00:00');
        $eligibleTwo = $this->createPairWithPrice($type, 'GBP', 'USD', $timeframe, '2026-10-10 11:00:00');
        $excludedPair = $this->createPairWithPrice($type, 'EUR', 'GBP', $timeframe, '2026-10-10 12:00:00');
        $this->createPairWithPrice($type, 'AUD', 'USD', $otherTimeframe, '2026-10-10 10:00:00');
        $this->createPairWithPrice($type, 'NZD', 'USD', $timeframe, '2026-10-12 10:00:00');
        $otherType = MarketType::create(['name' => 'Crypto']);
        $this->createPairWithPrice($otherType, 'BTC', 'USD', $timeframe, '2026-10-10 10:00:00');

        $this->get('/workbench/tests/create')
            ->assertOk()
            ->assertSee('Exclude pairs')
            ->assertSee('Shared portfolio capital')
            ->assertSee('Separate capital per pair')
            ->assertSee('EUR/GBP');

        $this->post('/workbench/tests', $this->requestPayload([
            'test_type' => 'market_type',
            'market_type_id' => $type->id,
            'timeframe_type_id' => $timeframe->id,
            'excluded_pair_ids' => [$excludedPair->id],
            'slippage_enabled' => '1',
            'slippage_pips' => '1.5',
            'slippage_pip_size' => '0.01',
            'partial_fill_enabled' => '1',
            'partial_fill_ratio' => '0.25',
            'unfilled_order_policy' => 'cancel',
        ]))->assertRedirect('/workbench/tests/market-type-runs/1');

        $run = MarketTypeBacktestRun::query()->findOrFail(1);
        $this->assertSame('running', $run->status);
        $this->assertSame(2, $run->total_pairs);
        $this->assertSame(2, $run->pairRuns()->where('status', 'pending')->count());
        $this->assertFalse($run->pairRuns()->where('pair_id', $excludedPair->id)->exists());
        $this->assertArrayNotHasKey('test_type', $run->configuration);
        $this->assertArrayNotHasKey('excluded_pair_ids', $run->configuration);
        $this->assertSame('1h', $run->configuration['timeframe_name']);
        $this->assertSame('shared', $run->configuration['capital_mode']);
        $this->assertTrue(filter_var($run->configuration['slippage_enabled'], FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('1.5', $run->configuration['slippage_pips']);
        $this->assertSame('0.01', $run->configuration['slippage_pip_size']);
        $this->assertTrue(filter_var($run->configuration['partial_fill_enabled'], FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('0.25', $run->configuration['partial_fill_ratio']);
        $this->assertSame('cancel', $run->configuration['unfilled_order_policy']);
        Bus::assertDispatched(RunMarketTypeBacktestPairJob::class, 2);

        foreach ([$eligibleOne, $eligibleTwo] as $pair) {
            $pairRun = $run->pairRuns()->where('pair_id', $pair->id)->firstOrFail();
            Bus::assertDispatched(
                RunMarketTypeBacktestPairJob::class,
                static fn (RunMarketTypeBacktestPairJob $job): bool => $job->pairRunId === $pairRun->id,
            );
        }

        $this->get("/workbench/tests/market-type-runs/{$run->id}")
            ->assertOk()
            ->assertSee('Forex')
            ->assertSee('1h')
            ->assertSee('EUR/USD')
            ->assertSee('PENDING');
    }

    public function test_invalid_market_type_is_rejected_and_empty_eligible_set_completes_immediately(): void
    {
        Bus::fake();
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);

        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', $this->requestPayload([
                'test_type' => 'market_type',
                'market_type_id' => 999,
                'timeframe_type_id' => $timeframe->id,
            ]))
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors('market_type_id');
        $this->assertDatabaseCount('market_type_backtest_run', 0);

        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', $this->requestPayload([
                'market_type_id' => $type->id,
                'timeframe_type_id' => $timeframe->id,
                'capital_mode' => 'invalid',
            ]))
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors('capital_mode');
        $this->assertDatabaseCount('market_type_backtest_run', 0);

        $this->post('/workbench/tests', $this->requestPayload([
            'test_type' => 'market_type',
            'market_type_id' => $type->id,
            'timeframe_type_id' => $timeframe->id,
            'capital_mode' => 'separate',
        ]))->assertRedirect('/workbench/tests/market-type-runs/1');

        $run = MarketTypeBacktestRun::query()->findOrFail(1);
        $this->assertSame('separate', $run->configuration['capital_mode']);
        $this->assertSame(0, $run->total_pairs);
        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->completed_at);
        Bus::assertNotDispatched(RunMarketTypeBacktestPairJob::class);
    }

    public function test_parallel_test_rejects_excluding_a_pair_from_another_market_type(): void
    {
        Bus::fake();
        $type = MarketType::create(['name' => 'Forex']);
        $otherType = MarketType::create(['name' => 'Crypto']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $foreignPair = $this->createPairWithPrice($otherType, 'BTC', 'USD', $timeframe, '2026-10-10 10:00:00');
        $this->createPairWithPrice($type, 'EUR', 'USD', $timeframe, '2026-10-10 10:00:00');

        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', $this->requestPayload([
                'market_type_id' => $type->id,
                'timeframe_type_id' => $timeframe->id,
                'excluded_pair_ids' => [$foreignPair->id],
            ]))
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors('excluded_pair_ids.0');

        $this->assertDatabaseCount('market_type_backtest_run', 0);
        Bus::assertNotDispatched(RunMarketTypeBacktestPairJob::class);
    }

    public function test_parallel_test_rejects_sync_queue_driver_instead_of_running_inline(): void
    {
        Bus::fake();
        config(['queue.default' => 'sync']);
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $this->createPairWithPrice($type, 'EUR', 'USD', $timeframe, '2026-10-10 10:00:00');

        $this->from('/workbench/tests/create')
            ->post('/workbench/tests', $this->requestPayload([
                'test_type' => 'market_type',
                'market_type_id' => $type->id,
                'timeframe_type_id' => $timeframe->id,
            ]))
            ->assertRedirect('/workbench/tests/create')
            ->assertSessionHasErrors('test_type');

        $this->assertDatabaseCount('market_type_backtest_run', 0);
        Bus::assertNotDispatched(RunMarketTypeBacktestPairJob::class);
    }

    public function test_pair_job_reuses_specific_pair_action_and_duplicate_delivery_does_not_rewrite_result(): void
    {
        $pair = $this->createPairWithPrice(
            MarketType::create(['name' => 'Forex']),
            'EUR',
            'USD',
            TimeframeType::create(['name' => '1h']),
            '2026-10-10 10:00:00',
        );
        $run = $this->createRun($pair);
        $pairRun = $run->pairRuns()->firstOrFail();
        $result = $this->configuredBacktestResult('EUR/USD');
        $specificBacktest = Mockery::mock(RunConfiguredPairBacktestAction::class);
        $specificBacktest->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(static fn (ConfiguredPairBacktestInput $input): bool => $input->marketPairId === $pair->id
                && $input->timeframeTypeId === 1))
            ->andReturn($result);

        $job = new RunMarketTypeBacktestPairJob($pairRun->id);
        $recorder = app(RecordMarketTypeBacktestPairResultAction::class);
        $runs = app(MarketTypeBacktestRunRepository::class);
        $job->handle($specificBacktest, $recorder, $runs);
        $job->handle($specificBacktest, $recorder, $runs);

        $pairRun->refresh();
        $this->assertSame('succeeded', $pairRun->status);
        $this->assertEquals($result->toPersistedArray(), $pairRun->result);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_successful_pair_result_opens_a_saved_analysis_page_from_the_run_table(): void
    {
        $pair = $this->createPairWithPrice(
            MarketType::create(['name' => 'Forex']),
            'EUR',
            'USD',
            TimeframeType::create(['name' => '1h']),
            '2026-10-10 10:00:00',
        );
        $run = $this->createRun($pair);
        $pairRun = $run->pairRuns()->firstOrFail();
        $pairRun->update([
            'status' => 'succeeded',
            'result' => [
                'pair' => 'EUR/USD',
                'timeframe' => '1h',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-11 00:00:00',
                'initial_capital' => 10000.0,
                'ending_capital' => 10000.0,
                'results' => [[
                    'candle' => ['price_date' => '2026-10-10 10:00:00', 'close' => '1.100000'],
                    'result' => false,
                    'signal' => 'none',
                    'quantity' => null,
                    'quantity_unit' => 'units',
                    'stop_loss' => null,
                    'take_profit' => null,
                ]],
                'order_history' => [],
            ],
            'completed_at' => now(),
        ]);

        $this->get("/workbench/tests/market-type-runs/{$run->id}")
            ->assertOk()
            ->assertSee('Detail ↗')
            ->assertSee('target="_blank"', false)
            ->assertDontSee('View saved result');

        $this->get("/workbench/tests/market-type-runs/{$run->id}/pairs/{$pairRun->id}")
            ->assertOk()
            ->assertSee('EUR/USD')
            ->assertSee('Capital movement')
            ->assertSee('Order history')
            ->assertSee('Candle decisions')
            ->assertSee('CLUSTER ANALYSIS')
            ->assertSee('Fluctuation Stress Test')
            ->assertSee('Monte Carlo Simulation')
            ->assertSee('2026-10-10 10:00:00');
    }

    public function test_overall_result_combines_successful_pairs_and_uses_shared_analysis_controls(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $pairOne = $this->createPairWithPrice($type, 'EUR', 'USD', $timeframe, '2026-10-10 10:00:00');
        $pairTwo = $this->createPairWithPrice($type, 'GBP', 'USD', $timeframe, '2026-10-10 11:00:00');
        $run = $this->createRun($pairOne, $pairTwo);
        $run->update(['status' => 'completed_with_failures']);
        $run->update(['configuration' => [...$run->configuration, 'capital_mode' => 'separate']]);

        $successfulRun = $run->pairRuns()->where('pair_id', $pairOne->id)->firstOrFail();
        $successfulRun->update([
            'status' => 'succeeded',
            'result' => [
                'pair' => 'EUR/USD',
                'timeframe' => '1h',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-11 00:00:00',
                'initial_capital' => 10000.0,
                'ending_capital' => 10100.0,
                'results' => [[
                    'candle' => ['price_date' => '2026-10-10 10:00:00', 'close' => '1.100000'],
                    'result' => false,
                    'signal' => 'none',
                    'quantity' => null,
                    'quantity_unit' => 'units',
                    'stop_loss' => null,
                    'take_profit' => null,
                ]],
                'order_history' => [[
                    'id' => 1,
                    'signal' => 'long',
                    'open_date' => '2026-10-10 10:00:00',
                    'close_date' => '2026-10-10 10:30:00',
                    'entry_price' => 1.1,
                    'entry_price_source' => 'ask_open',
                    'stop_loss' => 1.09,
                    'take_profit' => 1.12,
                    'close_reason' => 'take_profit',
                    'close_price' => 1.12,
                    'strategy_quantity' => 10000.0,
                    'quantity_unit' => 'units',
                    'quantity_multiplier' => 1.0,
                    'pnl_multiplier' => 1.0,
                    'closest_to_stop_loss' => null,
                    'closest_to_take_profit' => null,
                    'adverse_extreme' => null,
                    'favorable_extreme' => null,
                    'risk_amount' => 100.0,
                    'requested_risk_amount' => 100.0,
                    'position_size' => 10000.0,
                    'requested_position_size' => 10000.0,
                    'remaining_entry_quantity' => 0.0,
                    'remaining_position_size' => 0.0,
                    'closed_position_size' => 10000.0,
                    'cancelled_entry_quantity' => 0.0,
                    'cancelled_exit_quantity' => 0.0,
                    'entry_fills' => [],
                    'exit_fills' => [],
                    'realized_profit_loss' => 100.0,
                    'capital_at_open' => 10000.0,
                    'profit_loss' => 100.0,
                    'capital_after_close' => 10100.0,
                ]],
            ],
            'completed_at' => now(),
        ]);

        $failedRun = $run->pairRuns()->where('pair_id', $pairTwo->id)->firstOrFail();
        $failedRun->update([
            'status' => 'succeeded',
            'result' => [
                'initial_capital' => 8000.0,
                'ending_capital' => 9000.0,
                'results' => [],
                'order_history' => [],
            ],
            'completed_at' => now(),
        ]);

        $this->get("/workbench/tests/market-type-runs/{$run->id}")
            ->assertOk()
            ->assertSee('Overall result ↗');

        $this->get("/workbench/tests/market-type-runs/{$run->id}/overall")
            ->assertOk()
            ->assertSee('Overall market-type results')
            ->assertSee('2 successful pair(s)')
            ->assertSee('Separate capital per pair')
            ->assertSee('Overall initial and ending capital are sums')
            ->assertSee('AVERAGE INITIAL CAPITAL / PAIR')
            ->assertSee('AVERAGE ENDING CAPITAL / PAIR')
            ->assertSee('9,000.00')
            ->assertSee('9,550.00')
            ->assertSee('EUR/USD')
            ->assertSee('CLUSTER ANALYSIS')
            ->assertSee('Fluctuation Stress Test')
            ->assertSee('Monte Carlo Simulation')
            ->assertSee('100.00')
            ->assertSee('1.100000');

        $run->update(['configuration' => [...$run->configuration, 'capital_mode' => 'shared']]);
        $this->get("/workbench/tests/market-type-runs/{$run->id}/overall")
            ->assertOk()
            ->assertSee('Shared portfolio capital')
            ->assertSee('synchronizes entries and exits chronologically')
            ->assertSee('same account unit')
            ->assertDontSee('AVERAGE INITIAL CAPITAL / PAIR');

        $this->get("/workbench/tests/market-type-runs/{$run->id}/overall?stress_x=0.5")
            ->assertOk()
            ->assertSee('STRESS SCENARIO')
            ->assertSee('100.00');

        $this->get("/workbench/tests/market-type-runs/{$run->id}/overall?monte_carlo=1&simulation_count=10&method=shuffle&mc_initial_capital=10000&risk_model=fixed_monetary_pl&drawdown_threshold=20")
            ->assertOk()
            ->assertSee('FINAL EQUITY DISTRIBUTION');
    }

    public function test_parent_completes_after_all_pair_jobs_and_retains_success_when_another_pair_fails(): void
    {
        $type = MarketType::create(['name' => 'Forex']);
        $timeframe = TimeframeType::create(['name' => '1h']);
        $pairOne = $this->createPairWithPrice($type, 'EUR', 'USD', $timeframe, '2026-10-10 10:00:00');
        $pairTwo = $this->createPairWithPrice($type, 'GBP', 'USD', $timeframe, '2026-10-10 10:00:00');
        $run = $this->createRun($pairOne, $pairTwo);
        $pairRuns = $run->pairRuns()->orderBy('id')->get();

        $specificBacktest = Mockery::mock(RunConfiguredPairBacktestAction::class);
        $specificBacktest->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(ConfiguredPairBacktestInput::class))
            ->andReturn($this->configuredBacktestResult($pairRuns[0]->pair_name));
        (new RunMarketTypeBacktestPairJob($pairRuns[0]->id))->handle(
            $specificBacktest,
            app(RecordMarketTypeBacktestPairResultAction::class),
            app(MarketTypeBacktestRunRepository::class),
        );

        $this->assertSame('running', $run->fresh()->status);
        $this->assertNull($run->fresh()->completed_at);

        (new RunMarketTypeBacktestPairJob($pairRuns[1]->id))->failed(new RuntimeException('sensitive internal detail'));

        $this->assertSame('completed_with_failures', $run->fresh()->status);
        $this->assertSame(1, $run->pairRuns()->where('status', 'succeeded')->count());
        $this->assertSame(1, $run->pairRuns()->where('status', 'failed')->count());
        $this->assertSame(
            'Backtest failed. Check application logs for details.',
            $run->pairRuns()->where('status', 'failed')->value('failure_message'),
        );
    }

    public function test_dispatch_failure_marks_only_that_pair_failed_and_finishes_run(): void
    {
        $pair = $this->createPairWithPrice(
            MarketType::create(['name' => 'Forex']),
            'EUR',
            'USD',
            TimeframeType::create(['name' => '1h']),
            '2026-10-10 10:00:00',
        );
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(RunMarketTypeBacktestPairJob::class))
            ->andThrow(new RuntimeException('queue unavailable'));
        $action = new InitiateMarketTypeBacktestAction(
            $dispatcher,
            app(MarketPairRepository::class),
            app(MarketTypeRepository::class),
            app(TimeframeTypeRepository::class),
            app(MarketTypeBacktestRunRepository::class),
            app(RecordMarketTypeBacktestPairResultAction::class),
        );

        $run = $action->execute([
            'market_type_id' => $pair->market_type_id,
            'timeframe_type_id' => $pair->prices()->value('timeframe_type_id'),
            'initial_capital' => '10000.00',
            'from' => '2026-10-10 00:00:00',
            'to' => '2026-10-11 00:00:00',
            'active_from' => '09:00',
            'active_until' => '17:00',
            'test_type' => 'market_type',
        ]);

        $this->assertSame('completed_with_failures', $run->status);
        $this->assertSame('failed', $run->pairRuns()->firstOrFail()->status);
    }

    private function createPairWithPrice(
        MarketType $marketType,
        string $base,
        string $quote,
        TimeframeType $timeframe,
        string $priceDate,
    ): MarketPair {
        $pair = MarketPair::create([
            'market_type_id' => $marketType->id,
            'base' => $base,
            'quote' => $quote,
        ]);
        MarketPrice::create([
            'pair_id' => $pair->id,
            'timeframe_type_id' => $timeframe->id,
            'price_date' => $priceDate,
            'open' => '1.100000',
            'high' => '1.120000',
            'low' => '1.090000',
            'close' => '1.110000',
            'bid_open' => '1.099000',
            'bid_high' => '1.119000',
            'bid_low' => '1.089000',
            'bid_close' => '1.109000',
            'ask_open' => '1.101000',
            'ask_high' => '1.121000',
            'ask_low' => '1.091000',
            'ask_close' => '1.111000',
        ]);

        return $pair;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function requestPayload(array $overrides): array
    {
        return array_merge([
            'test_type' => 'market_type',
            'market_type_id' => 1,
            'timeframe_type_id' => 1,
            'initial_capital' => '10000.00',
            'from' => '2026-10-10T00:00',
            'to' => '2026-10-11T00:00',
            'active_from' => '09:00',
            'active_until' => '17:00',
        ], $overrides);
    }

    private function createRun(MarketPair ...$pairs): MarketTypeBacktestRun
    {
        $run = MarketTypeBacktestRun::query()->create([
            'market_type_id' => $pairs[0]->market_type_id,
            'market_type_name' => 'Forex',
            'configuration' => [
                'timeframe_type_id' => 1,
                'timeframe_name' => '1h',
                'initial_capital' => '10000.00',
                'capital_mode' => 'shared',
                'from' => '2026-10-10 00:00:00',
                'to' => '2026-10-11 00:00:00',
                'active_from' => '09:00',
                'active_until' => '17:00',
            ],
            'total_pairs' => count($pairs),
            'status' => 'running',
            'started_at' => now(),
        ]);
        foreach ($pairs as $pair) {
            $run->pairRuns()->create([
                'pair_id' => $pair->id,
                'pair_name' => $pair->base.'/'.$pair->quote,
                'status' => 'pending',
            ]);
        }

        return $run;
    }

    private function configuredBacktestResult(string $pair): ConfiguredPairBacktestResult
    {
        return new ConfiguredPairBacktestResult(
            pair: $pair,
            timeframe: '1h',
            from: new \DateTimeImmutable('2026-10-10 00:00:00'),
            to: new \DateTimeImmutable('2026-10-11 00:00:00'),
            backtest: new SpecificPairBacktestResult(
                decisions: Vec::empty(),
                orders: Vec::empty(),
                initialCapital: 10000.0,
                quantityUnit: 'units',
                unitsPerLot: 1.0,
                quantityMultiplier: 1.0,
                endingCapital: 10000.0,
                executionConfiguration: new BacktestExecutionConfiguration(
                    new FixedSlippageConfig,
                    new PartialFillConfig,
                ),
            ),
        );
    }
}
