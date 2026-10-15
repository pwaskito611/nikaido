<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Config;

use InvalidArgumentException;

class MonteCarloSimulationConfig
{
    public const MAX_SIMULATED_TRADES = 10000000;

    public const METHOD_SHUFFLE = 'shuffle';

    public const METHOD_BOOTSTRAP = 'bootstrap';

    public const RISK_MODEL_FIXED_MONETARY_PL = 'fixed_monetary_pl';

    public const RISK_MODEL_COMPOUNDED_RETURN = 'compounded_return';

    public function __construct(
        public readonly int $simulationCount = 1000,
        public readonly ?int $tradesPerSimulation = null,
        public readonly string $method = self::METHOD_SHUFFLE,
        public readonly float $initialCapital = 10000.0,
        public readonly ?int $randomSeed = null,
        public readonly string $riskModel = self::RISK_MODEL_FIXED_MONETARY_PL,
        public readonly float $drawdownThreshold = 20.0,
    ) {
        if ($simulationCount < 1 || $simulationCount > 10000) {
            throw new InvalidArgumentException('Simulation count must be between 1 and 10,000.');
        }

        if ($tradesPerSimulation !== null && ($tradesPerSimulation < 1 || $tradesPerSimulation > 10000)) {
            throw new InvalidArgumentException('Trades per simulation must be between 1 and 10,000.');
        }

        if (! in_array($method, [self::METHOD_SHUFFLE, self::METHOD_BOOTSTRAP], true)) {
            throw new InvalidArgumentException('Monte Carlo method must be shuffle or bootstrap.');
        }

        if (! is_finite($initialCapital) || $initialCapital <= 0) {
            throw new InvalidArgumentException('Initial capital must be a positive finite number.');
        }

        if ($randomSeed !== null && ($randomSeed < 0 || $randomSeed > 2147483647)) {
            throw new InvalidArgumentException('Random seed must be between 0 and 2,147,483,647.');
        }

        if (! in_array($riskModel, [self::RISK_MODEL_FIXED_MONETARY_PL, self::RISK_MODEL_COMPOUNDED_RETURN], true)) {
            throw new InvalidArgumentException('Risk model must be fixed_monetary_pl or compounded_return.');
        }

        if (! is_finite($drawdownThreshold) || $drawdownThreshold < 0 || $drawdownThreshold > 100) {
            throw new InvalidArgumentException('Drawdown threshold must be between 0 and 100 percent.');
        }
    }
}
