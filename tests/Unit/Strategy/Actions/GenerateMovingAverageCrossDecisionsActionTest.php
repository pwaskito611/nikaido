<?php

namespace Tests\Unit\Strategy\Actions;

use App\Feature\MarketPrice\Model\MarketPrice;
use App\Platform\Strategy\Actions\GenerateMovingAverageCrossDecisionsAction;
use App\Platform\Strategy\Enums\StrategySignal;
use App\Platform\Strategy\Models\StrategyDecision;
use App\Platform\Strategy\Models\StrategyExecutionContext;
use Exact\Collection\Vec;
use Tests\TestCase;

class GenerateMovingAverageCrossDecisionsActionTest extends TestCase
{
    public function test_it_emits_long_after_bullish_ema_cross_above_sma_200_with_two_to_one_reward(): void
    {
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'ask_open' => null,
                'bid_low' => 129.0,
            ] : []),
            [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0],
            array_keys([...range(0, 199), 200]),
        );

        $decisions = $this->decisions($candles);

        $this->assertSame(StrategySignal::Long, $decisions->at(200)->signal);
        $this->assertSame(129.0, $decisions->at(200)->stopLoss);
        $this->assertSame(132.0, $decisions->at(200)->takeProfit);
        $this->assertSame(100.0, $decisions->at(200)->quantity);
    }

    public function test_it_emits_short_after_bearish_ema_cross_below_sma_200_with_two_to_one_reward(): void
    {
        $closes = [...array_map(static fn (int $index): float => 80 + $index * 0.1, range(0, 199)), 70.0];
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'bid_open' => 69.0,
                'ask_high' => 71.0,
            ] : []),
            $closes,
            array_keys($closes),
        );

        $decisions = $this->decisions($candles);

        $this->assertSame(StrategySignal::Short, $decisions->at(200)->signal);
        $this->assertSame(71.0, $decisions->at(200)->stopLoss);
        $this->assertSame(65.0, $decisions->at(200)->takeProfit);
    }

    public function test_it_falls_back_to_low_when_bid_low_is_unavailable(): void
    {
        $closes = [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0];
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'ask_open' => 131.0,
                'bid_low' => null,
                'low' => 128.0,
            ] : []),
            $closes,
            array_keys($closes),
        );

        $decisions = $this->decisions($candles);

        $this->assertSame(StrategySignal::Long, $decisions->at(200)->signal);
        $this->assertSame(128.0, $decisions->at(200)->stopLoss);
    }

    public function test_it_falls_back_to_high_when_ask_high_is_unavailable(): void
    {
        $closes = [...array_map(static fn (int $index): float => 80 + $index * 0.1, range(0, 199)), 70.0];
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'bid_open' => 69.0,
                'ask_high' => null,
                'high' => 72.0,
            ] : []),
            $closes,
            array_keys($closes),
        );

        $decisions = $this->decisions($candles);

        $this->assertSame(StrategySignal::Short, $decisions->at(200)->signal);
        $this->assertSame(72.0, $decisions->at(200)->stopLoss);
    }

    public function test_it_returns_no_signal_before_200_candles_are_available(): void
    {
        $candles = array_map(fn (): MarketPrice => $this->candle(100.0), range(1, 199));

        $decisions = $this->decisions($candles);

        $this->assertCount(199, $decisions);
        $this->assertSame(StrategySignal::None, $decisions->at(198)->signal);
    }

    public function test_it_rejects_a_bullish_cross_when_price_is_below_the_sma_200(): void
    {
        $closes = [...array_fill(0, 100, 150.0), ...array_fill(0, 100, 100.0), 115.0];
        $candles = array_map(
            fn (float $close): MarketPrice => $this->candle($close),
            $closes,
        );

        $decisions = $this->decisions($candles);

        $this->assertSame(StrategySignal::None, $decisions->at(200)->signal);
    }

    public function test_it_sizes_entries_to_one_percent_of_the_current_capital(): void
    {
        $closes = [
            ...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)),
            130.0,
            100.0,
            140.0,
        ];
        $candles = array_map(
            fn (float $close): MarketPrice => $this->candle($close),
            $closes,
        );
        $context = new StrategyExecutionContext(10000.0, 'units', 1.0);
        $firstQuantity = null;
        $secondQuantity = null;

        foreach ((new GenerateMovingAverageCrossDecisionsAction)->execute($candles, $context) as $index => $decision) {
            if ($index === 200) {
                $this->assertSame(StrategySignal::Long, $decision->signal);
                $firstQuantity = $decision->quantity;
                $context->currentCapital = 20000.0;
            }

            if ($index === 202) {
                $this->assertSame(StrategySignal::Long, $decision->signal);
                $secondQuantity = $decision->quantity;
            }
        }

        $this->assertEqualsWithDelta(100.0, $firstQuantity, 0.000001);
        $this->assertEqualsWithDelta(200.0, $secondQuantity, 0.000001);
    }

    public function test_it_accounts_for_contract_multiplier_when_sizing_risk(): void
    {
        $closes = [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0];
        $candles = array_map(
            fn (float $close): MarketPrice => $this->candle($close),
            $closes,
        );
        $context = new StrategyExecutionContext(10000.0, 'contract', 50.0);

        foreach ((new GenerateMovingAverageCrossDecisionsAction)->execute($candles, $context) as $index => $decision) {
            if ($index === 200) {
                $this->assertSame(StrategySignal::Long, $decision->signal);
                $this->assertSame(2.0, $decision->quantity);
                $this->assertSame(
                    100.0,
                    $decision->quantity * abs($candles[$index]->ask_open - $decision->stopLoss) * 50,
                );
            }
        }
    }

    public function test_it_sizes_lots_from_the_risk_budget_and_price_distance(): void
    {
        $closes = [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0];
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'ask_open' => 130.0,
                'bid_low' => 129.0,
            ] : []),
            $closes,
            array_keys($closes),
        );
        $context = new StrategyExecutionContext(10000.0, 'lot', 100000.0);

        foreach ((new GenerateMovingAverageCrossDecisionsAction)->execute($candles, $context) as $index => $decision) {
            if ($index === 200) {
                $this->assertSame(StrategySignal::Long, $decision->signal);
                $this->assertSame(0.001, $decision->quantity);
                $this->assertSame(
                    100.0,
                    $decision->quantity * abs($candles[$index]->ask_open - $decision->stopLoss) * 100000,
                );
            }
        }
    }

    public function test_it_skips_entries_when_current_capital_cannot_fund_a_positive_position(): void
    {
        $closes = [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0];
        $candles = array_map(
            fn (float $close): MarketPrice => $this->candle($close),
            $closes,
        );
        $context = new StrategyExecutionContext(10000.0, 'units', 1.0);
        $context->currentCapital = -1.0;

        foreach ((new GenerateMovingAverageCrossDecisionsAction)->execute($candles, $context) as $index => $decision) {
            if ($index === 200) {
                $this->assertSame(StrategySignal::None, $decision->signal);
                $this->assertNull($decision->quantity);
            }
        }
    }

    /**
     * @param  array<string, float|null>  $quotes
     */
    private function candle(float $close, array $quotes = []): MarketPrice
    {
        return new MarketPrice([
            'close' => $close,
            'open' => $close,
            'high' => $close + 1,
            'low' => $close - 1,
            'ask_open' => $close,
            'ask_high' => $close + 1,
            'ask_low' => $close - 1,
            'ask_close' => $close,
            'bid_open' => $close,
            'bid_high' => $close + 1,
            'bid_low' => $close - 1,
            'bid_close' => $close,
            ...$quotes,
        ]);
    }

    /**
     * @param  iterable<MarketPrice>  $candles
     * @return Vec<StrategyDecision>
     */
    private function decisions(iterable $candles, float $capital = 10000.0): Vec
    {
        return Vec::fromArray(iterator_to_array(
            (new GenerateMovingAverageCrossDecisionsAction)->execute(
                $candles,
                new StrategyExecutionContext($capital, 'units', 1.0),
            ),
            false,
        ));
    }
}
