<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use InvalidArgumentException;

final readonly class MonteCarloSimulationMetrics
{
    public function __construct(
        public MonteCarloEquityMetrics $finalEquity,
        public MonteCarloDrawdownMetrics $maximumDrawdownPercent,
        public MonteCarloDrawdownMetrics $maximumDrawdownAmount,
        public float $probabilityOfLoss,
        public float $probabilityOfDrawdown,
    ) {
        foreach ([$probabilityOfLoss, $probabilityOfDrawdown] as $probability) {
            if (! is_finite($probability) || $probability < 0 || $probability > 100) {
                throw new InvalidArgumentException('Monte Carlo probabilities must be between 0 and 100 percent.');
            }
        }
    }

    /**
     * @return array{
     *     final_equity: array{median: float, mean: float, p5: float, p25: float, p75: float, p95: float},
     *     maximum_drawdown_percent: array{median: float, p95: float, worst: float},
     *     maximum_drawdown_amount: array{median: float, p95: float, worst: float},
     *     probability_of_loss: float,
     *     probability_of_drawdown: float
     * }
     */
    public function toArray(): array
    {
        return [
            'final_equity' => $this->finalEquity->toArray(),
            'maximum_drawdown_percent' => $this->maximumDrawdownPercent->toArray(),
            'maximum_drawdown_amount' => $this->maximumDrawdownAmount->toArray(),
            'probability_of_loss' => $this->probabilityOfLoss,
            'probability_of_drawdown' => $this->probabilityOfDrawdown,
        ];
    }
}
