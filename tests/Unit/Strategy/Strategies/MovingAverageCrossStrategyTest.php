<?php

namespace Tests\Unit\Strategy\Strategies;

use App\Engine\Models\MarketPrice;
use App\Strategy\Enums\StrategySignal;
use App\Strategy\Strategies\MovingAverageCrossStrategy;
use Tests\TestCase;

class MovingAverageCrossStrategyTest extends TestCase
{
    public function test_it_emits_long_after_bullish_ema_cross_above_sma_200_with_two_to_one_reward(): void
    {
        $candles = array_map(
            fn (float $close, int $index): MarketPrice => $this->candle($close, $index === 200 ? [
                'ask_open' => 131.0,
                'bid_low' => 129.0,
            ] : []),
            [...array_map(static fn (int $index): float => 120 - $index * 0.1, range(0, 199)), 130.0],
            array_keys([...range(0, 199), 200]),
        );

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

        $this->assertSame(StrategySignal::Long, $decisions->at(200)->signal);
        $this->assertSame(129.0, $decisions->at(200)->stopLoss);
        $this->assertSame(135.0, $decisions->at(200)->takeProfit);
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

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

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

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

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

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

        $this->assertSame(StrategySignal::Short, $decisions->at(200)->signal);
        $this->assertSame(72.0, $decisions->at(200)->stopLoss);
    }

    public function test_it_returns_no_signal_before_200_candles_are_available(): void
    {
        $candles = array_map(fn (): MarketPrice => $this->candle(100.0), range(1, 199));

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

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

        $decisions = (new MovingAverageCrossStrategy)->execute($candles);

        $this->assertSame(StrategySignal::None, $decisions->at(200)->signal);
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
}
