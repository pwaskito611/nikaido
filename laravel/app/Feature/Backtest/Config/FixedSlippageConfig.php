<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

use InvalidArgumentException;

class FixedSlippageConfig
{
    public readonly float $pips;

    public readonly float $fixedSlippage;

    public function __construct(
        public readonly bool $enabled = false,
        float $pips = 0.0,
        public readonly ?float $pipSize = null,
        public readonly SlippageMode $mode = SlippageMode::Fixed,
        ?float $fixedSlippage = null,
        public readonly float $baseSlippage = 0.0,
        public readonly float $volatilityFactor = 0.0,
        public readonly float $volumeImpactFactor = 0.0,
        public readonly float $spreadFactor = 0.0,
        public readonly float $maximumSlippage = 10.0,
        public readonly float $minimumSlippage = 0.0,
        public readonly SlippageDistribution $distribution = SlippageDistribution::Uniform,
        public readonly float $mean = 0.0,
        public readonly float $standardDeviation = 1.0,
        public readonly ?int $randomSeed = null,
        public readonly string $unit = 'pips',
        public readonly SlippageDirection $direction = SlippageDirection::AdverseOnly,
    ) {
        $fixedSlippage ??= $pips;
        if (! is_finite($fixedSlippage) || $fixedSlippage < 0) {
            throw new InvalidArgumentException('Slippage must be a non-negative finite number of pips.');
        }
        $this->pips = $fixedSlippage;
        $this->fixedSlippage = $fixedSlippage;

        if ($pipSize !== null && (! is_finite($pipSize) || $pipSize <= 0)) {
            throw new InvalidArgumentException('Slippage pip size must be a positive finite price increment.');
        }
        foreach ([
            'base slippage' => $this->baseSlippage,
            'volatility factor' => $this->volatilityFactor,
            'volume impact factor' => $this->volumeImpactFactor,
            'spread factor' => $this->spreadFactor,
            'maximum slippage' => $this->maximumSlippage,
            'minimum slippage' => $this->minimumSlippage,
            'normal mean' => $this->mean,
            'normal standard deviation' => $this->standardDeviation,
        ] as $label => $value) {
            if (! is_finite($value) || $value < 0) {
                throw new InvalidArgumentException("The {$label} must be a non-negative finite number of pips.");
            }
        }
        if ($this->mode === SlippageMode::Random && $this->minimumSlippage > $this->maximumSlippage) {
            throw new InvalidArgumentException('Minimum slippage cannot exceed maximum slippage.');
        }
        if ($this->mode === SlippageMode::Random
            && $this->distribution === SlippageDistribution::Normal
            && ($this->mean < $this->minimumSlippage || $this->mean > $this->maximumSlippage)) {
            throw new InvalidArgumentException('Normal slippage mean must be within its configured boundaries.');
        }
        if ($this->randomSeed !== null && ($this->randomSeed < 0 || $this->randomSeed > 2147483647)) {
            throw new InvalidArgumentException('The slippage random seed must be between 0 and 2147483647.');
        }
        if ($this->unit !== 'pips') {
            throw new InvalidArgumentException('Slippage is currently configured in pips.');
        }
    }
}
