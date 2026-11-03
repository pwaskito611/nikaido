<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Execution;

use App\Feature\Backtest\Config\FixedSlippageConfig;
use App\Feature\Backtest\Config\SlippageDirection;
use App\Feature\Backtest\Config\SlippageDistribution;
use App\Feature\Backtest\Config\SlippageMode;
use App\Feature\MarketPrice\Model\MarketPrice;
use Random\Engine\Mt19937;
use Random\Randomizer;
use UnexpectedValueException;

final class SlippageCalculator
{
    private const RANDOM_RESOLUTION = 9007199254740991;

    private readonly Randomizer $randomizer;

    private readonly FixedSlippage $fixedSlippage;

    public function __construct(
        private readonly FixedSlippageConfig $config,
    ) {
        $this->randomizer = new Randomizer(new Mt19937(
            $config->randomSeed ?? random_int(0, mt_getrandmax()),
        ));
        $this->fixedSlippage = new FixedSlippage;
    }

    public function apply(
        float $referencePrice,
        float $filledQuantity,
        float $pipSize,
        bool $isBuy,
        bool $isEntry,
        MarketPrice $candle,
    ): float {
        if (! is_finite($referencePrice) || $referencePrice <= 0
            || ! is_finite($filledQuantity) || $filledQuantity <= 0
            || ! is_finite($pipSize) || $pipSize <= 0) {
            throw new UnexpectedValueException('Slippage requires a positive finite reference price, fill quantity, and price increment.');
        }

        $slippagePips = match ($this->config->mode) {
            SlippageMode::Fixed => $this->config->fixedSlippage,
            SlippageMode::Dynamic => $this->dynamicSlippage($filledQuantity, $pipSize, $isEntry, $candle),
            SlippageMode::Random => $this->randomSlippage(),
        };
        $isAdverse = $this->config->direction === SlippageDirection::AdverseOnly
            || $this->randomizer->getInt(0, 1) === 1;
        $executionPrice = $this->fixedSlippage->apply(
            $referencePrice,
            $slippagePips,
            $pipSize,
            $isAdverse ? $isBuy : ! $isBuy,
        );
        if (! is_finite($executionPrice) || $executionPrice <= 0) {
            throw new UnexpectedValueException('Calculated slippage produced an invalid execution price.');
        }

        return $executionPrice;
    }

    private function dynamicSlippage(
        float $filledQuantity,
        float $pipSize,
        bool $isEntry,
        MarketPrice $candle,
    ): float {
        $volatilityPips = 0.0;
        if ($this->config->volatilityFactor > 0) {
            if (! is_numeric($candle->high) || ! is_numeric($candle->low)
                || ! is_finite((float) $candle->high) || ! is_finite((float) $candle->low)
                || (float) $candle->high < (float) $candle->low) {
                throw new UnexpectedValueException('Dynamic slippage requires valid candle high and low values when the volatility factor is enabled.');
            }
            $volatilityPips = ((float) $candle->high - (float) $candle->low) / $pipSize;
        }

        $volumeImpactPips = 0.0;
        if ($this->config->volumeImpactFactor > 0) {
            $volume = $candle->volume;
            if ($candle->volume_type === 'tick') {
                throw new UnexpectedValueException('Dynamic slippage cannot use tick volume as traded liquidity.');
            }
            if ($candle->volume_type !== 'traded'
                || ! is_numeric($volume)
                || ! is_finite((float) $volume)
                || (float) $volume <= 0) {
                throw new UnexpectedValueException('Dynamic slippage with a volume-impact factor requires positive, explicitly typed traded volume.');
            }
            $volumeImpactPips = sqrt($filledQuantity / (float) $volume);
        }

        $spreadPips = 0.0;
        if ($this->config->spreadFactor > 0) {
            $field = $isEntry ? 'open' : 'close';
            $bid = $candle->{'bid_'.$field};
            $ask = $candle->{'ask_'.$field};
            if (! is_numeric($bid) || ! is_numeric($ask)
                || ! is_finite((float) $bid) || ! is_finite((float) $ask)
                || (float) $ask < (float) $bid) {
                throw new UnexpectedValueException("Dynamic slippage requires a valid bid/ask {$field} spread when the spread factor is enabled.");
            }
            $spreadPips = ((float) $ask - (float) $bid) / $pipSize;
        }

        $slippage = $this->config->baseSlippage
            + $this->config->volatilityFactor * $volatilityPips
            + $this->config->volumeImpactFactor * $volumeImpactPips
            + $this->config->spreadFactor * $spreadPips;
        if (! is_finite($slippage)) {
            throw new UnexpectedValueException('Dynamic slippage calculation overflowed.');
        }

        return min($slippage, $this->config->maximumSlippage);
    }

    private function randomSlippage(): float
    {
        if ($this->config->distribution === SlippageDistribution::Uniform) {
            return $this->config->minimumSlippage
                + $this->uniformUnit() * ($this->config->maximumSlippage - $this->config->minimumSlippage);
        }

        if ($this->config->standardDeviation === 0) {
            return $this->config->mean;
        }

        $first = max($this->uniformUnit(), 1 / self::RANDOM_RESOLUTION);
        $second = $this->uniformUnit();
        $standardNormal = sqrt(-2 * log($first)) * cos(2 * M_PI * $second);
        $sample = $this->config->mean + $this->config->standardDeviation * $standardNormal;

        if (! is_finite($sample)) {
            throw new UnexpectedValueException('Random slippage calculation produced a non-finite value.');
        }

        return min($this->config->maximumSlippage, max($this->config->minimumSlippage, $sample));
    }

    private function uniformUnit(): float
    {
        return $this->randomizer->getInt(0, self::RANDOM_RESOLUTION) / self::RANDOM_RESOLUTION;
    }
}
