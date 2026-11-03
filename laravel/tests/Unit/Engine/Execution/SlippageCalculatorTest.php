<?php

declare(strict_types=1);

namespace Tests\Unit\Engine\Execution;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\SlippageDirection;
use App\Feature\Backtest\Config\SlippageDistribution;
use App\Feature\Backtest\Config\SlippageMode;
use App\Feature\Backtest\Execution\SlippageCalculator;
use App\Feature\MarketPrice\Model\MarketPrice;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class SlippageCalculatorTest extends TestCase
{
    public function test_fixed_slippage_is_consistent_and_moves_buy_and_sell_prices_adversely(): void
    {
        $calculator = new SlippageCalculator(new FixedSlippageConfig(enabled: true, pips: 2));
        $candle = $this->candle();

        $this->assertSame(1.1002, $calculator->apply(1.1, 10, 0.0001, true, true, $candle));
        $this->assertSame(1.0998, $calculator->apply(1.1, 10, 0.0001, false, false, $candle));
        $this->assertSame(1.1002, $calculator->apply(1.1, 10, 0.0001, true, true, $candle));
    }

    public function test_random_direction_can_be_favorable_or_adverse_and_is_reproducible(): void
    {
        $config = new FixedSlippageConfig(
            enabled: true,
            pips: 2,
            randomSeed: 4321,
            direction: SlippageDirection::Random,
        );
        $first = new SlippageCalculator($config);
        $second = new SlippageCalculator($config);
        $candle = $this->candle();
        $buyPrices = [];
        $sellPrices = [];
        $repeatedBuyPrices = [];
        $repeatedSellPrices = [];

        for ($index = 0; $index < 32; $index++) {
            $buyPrices[] = $first->apply(1.1, 10, 0.0001, true, true, $candle);
            $sellPrices[] = $first->apply(1.1, 10, 0.0001, false, false, $candle);
            $repeatedBuyPrices[] = $second->apply(1.1, 10, 0.0001, true, true, $candle);
            $repeatedSellPrices[] = $second->apply(1.1, 10, 0.0001, false, false, $candle);
        }

        $this->assertSame($buyPrices, $repeatedBuyPrices);
        $this->assertSame($sellPrices, $repeatedSellPrices);
        $this->assertContains(1.1002, $buyPrices);
        $this->assertContains(1.0998, $buyPrices);
        $this->assertContains(1.0998, $sellPrices);
        $this->assertContains(1.1002, $sellPrices);
    }

    public function test_dynamic_slippage_responds_to_volatility_volume_and_spread_and_obeys_cap(): void
    {
        $volatilityConfig = new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            volatilityFactor: 0.5,
            maximumSlippage: 100,
        );
        $quietCandle = $this->candle(['high' => 1.1002, 'low' => 1.1]);
        $volatileCandle = $this->candle(['high' => 1.102, 'low' => 1.1]);
        $quietResult = (new SlippageCalculator($volatilityConfig))->apply(1.1, 10, 0.0001, true, true, $quietCandle);
        $volatileResult = (new SlippageCalculator($volatilityConfig))->apply(1.1, 10, 0.0001, true, true, $volatileCandle);
        $this->assertGreaterThan($quietResult, $volatileResult);

        $volumeConfig = new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            volumeImpactFactor: 2,
            maximumSlippage: 100,
        );
        $highVolume = $this->candle(['volume' => 10000, 'volume_type' => 'traded']);
        $lowVolume = $this->candle(['volume' => 100, 'volume_type' => 'traded']);
        $highVolumeResult = (new SlippageCalculator($volumeConfig))->apply(1.1, 10, 0.0001, true, true, $highVolume);
        $lowVolumeResult = (new SlippageCalculator($volumeConfig))->apply(1.1, 10, 0.0001, true, true, $lowVolume);
        $this->assertGreaterThan($highVolumeResult, $lowVolumeResult);

        $spreadConfig = new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            spreadFactor: 0.5,
            maximumSlippage: 100,
        );
        $tightSpread = $this->candle(['bid_open' => 1.1, 'ask_open' => 1.1001]);
        $wideSpread = $this->candle(['bid_open' => 1.1, 'ask_open' => 1.1005]);
        $tightResult = (new SlippageCalculator($spreadConfig))->apply(1.1, 10, 0.0001, true, true, $tightSpread);
        $wideResult = (new SlippageCalculator($spreadConfig))->apply(1.1, 10, 0.0001, true, true, $wideSpread);
        $this->assertGreaterThan($tightResult, $wideResult);

        $cappedCalculator = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            baseSlippage: 3,
            volatilityFactor: 2,
            maximumSlippage: 4,
        ));
        $this->assertSame(1.1004, $cappedCalculator->apply(1.1, 10, 0.0001, true, true, $volatileCandle));
    }

    public function test_dynamic_volume_impact_rejects_missing_zero_or_tick_volume(): void
    {
        $calculator = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            volumeImpactFactor: 1,
        ));

        foreach ([
            $this->candle(),
            $this->candle(['volume' => 0, 'volume_type' => 'traded']),
            $this->candle(['volume' => -1, 'volume_type' => 'traded']),
            $this->candle(['volume' => 100, 'volume_type' => 'tick']),
        ] as $candle) {
            try {
                $calculator->apply(1.1, 10, 0.0001, true, true, $candle);
                $this->fail('Invalid market volume must not be interpreted as available liquidity.');
            } catch (UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_dynamic_spread_factor_rejects_invalid_quotes(): void
    {
        $calculator = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Dynamic,
            spreadFactor: 1,
        ));

        $this->expectException(UnexpectedValueException::class);
        $calculator->apply(1.1, 10, 0.0001, true, true, $this->candle([
            'bid_open' => 1.1002,
            'ask_open' => 1.1,
        ]));
    }

    public function test_random_slippage_stays_within_bounds_and_repeats_for_a_seed(): void
    {
        $config = new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Random,
            minimumSlippage: 1,
            maximumSlippage: 3,
            randomSeed: 4321,
        );
        $first = new SlippageCalculator($config);
        $second = new SlippageCalculator($config);
        $candle = $this->candle();

        for ($index = 0; $index < 20; $index++) {
            $firstPrice = $first->apply(1.1, 10, 0.0001, true, true, $candle);
            $secondPrice = $second->apply(1.1, 10, 0.0001, true, true, $candle);
            $this->assertGreaterThanOrEqual(1.1001, $firstPrice);
            $this->assertLessThanOrEqual(1.1003, $firstPrice);
            $this->assertSame($firstPrice, $secondPrice);
        }
    }

    public function test_random_slippage_changes_with_seed_and_clips_normal_samples_to_bounds(): void
    {
        $candle = $this->candle();
        $first = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Random,
            minimumSlippage: 0,
            maximumSlippage: 1,
            randomSeed: 1,
        ));
        $second = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Random,
            minimumSlippage: 0,
            maximumSlippage: 1,
            randomSeed: 2,
        ));
        $this->assertNotSame(
            $first->apply(1.1, 10, 0.0001, true, true, $candle),
            $second->apply(1.1, 10, 0.0001, true, true, $candle),
        );

        $normal = new SlippageCalculator(new FixedSlippageConfig(
            enabled: true,
            mode: SlippageMode::Random,
            minimumSlippage: 0.5,
            maximumSlippage: 1.5,
            distribution: SlippageDistribution::Normal,
            mean: 1,
            standardDeviation: 10,
            randomSeed: 7,
        ));
        for ($index = 0; $index < 20; $index++) {
            $price = $normal->apply(1.1, 10, 0.0001, true, true, $candle);
            $this->assertGreaterThanOrEqual(1.1000499999, $price);
            $this->assertLessThanOrEqual(1.1001500001, $price);
        }
    }

    private function candle(array $attributes = []): MarketPrice
    {
        return new MarketPrice([
            'high' => 1.1,
            'low' => 1.1,
            'bid_open' => 1.1,
            'ask_open' => 1.1,
            'bid_close' => 1.1,
            'ask_close' => 1.1,
            ...$attributes,
        ]);
    }
}
