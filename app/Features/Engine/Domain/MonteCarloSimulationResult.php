<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use App\Features\Engine\Config\MonteCarloSimulationConfig;
use Exact\Collection\Vec;
use InvalidArgumentException;

final readonly class MonteCarloSimulationResult
{
    /**
     * @param  Vec<float>  $finalEquities
     * @param  Vec<float>  $maximumDrawdownsPercent
     * @param  Vec<float>  $maximumDrawdownsAmount
     * @param  Vec<MonteCarloHistogramBin>  $finalEquityHistogram
     * @param  Vec<MonteCarloHistogramBin>  $maximumDrawdownPercentHistogram
     */
    public function __construct(
        public MonteCarloSimulationConfig $config,
        public int $tradesPerSimulation,
        public MonteCarloSimulationMetrics $metrics,
        public Vec $finalEquities,
        public Vec $maximumDrawdownsPercent,
        public Vec $maximumDrawdownsAmount,
        public Vec $finalEquityHistogram,
        public Vec $maximumDrawdownPercentHistogram,
    ) {
        if ($tradesPerSimulation < 1
            || ($config->tradesPerSimulation !== null && $config->tradesPerSimulation !== $tradesPerSimulation)) {
            throw new InvalidArgumentException('Monte Carlo result trade count must match its simulation configuration.');
        }

        foreach ([
            $finalEquities,
            $maximumDrawdownsPercent,
            $maximumDrawdownsAmount,
        ] as $distribution) {
            if ($distribution->count() !== $config->simulationCount) {
                throw new InvalidArgumentException('Monte Carlo result distributions must contain one value per simulation.');
            }
        }

        self::assertOrderedValues($finalEquities, minimum: 0.0);
        self::assertOrderedValues($maximumDrawdownsPercent, minimum: 0.0, maximum: 100.0);
        self::assertOrderedValues($maximumDrawdownsAmount, minimum: 0.0);
        self::assertHistogramCount($finalEquityHistogram, $config->simulationCount);
        self::assertHistogramCount($maximumDrawdownPercentHistogram, $config->simulationCount);
    }

    /**
     * @param  Vec<float>  $values
     */
    private static function assertOrderedValues(Vec $values, float $minimum, ?float $maximum = null): void
    {
        $previous = null;
        foreach ($values as $value) {
            if (! is_float($value)
                || ! is_finite($value)
                || $value < $minimum
                || ($maximum !== null && $value > $maximum)
                || ($previous !== null && $value < $previous)) {
                throw new InvalidArgumentException('Monte Carlo distributions must contain finite, ordered values within their domain.');
            }
            $previous = $value;
        }
    }

    /** @param Vec<MonteCarloHistogramBin> $histogram */
    private static function assertHistogramCount(Vec $histogram, int $simulationCount): void
    {
        $count = 0;
        foreach ($histogram as $bin) {
            if (! $bin instanceof MonteCarloHistogramBin) {
                throw new InvalidArgumentException('Monte Carlo histograms must contain histogram bins.');
            }
            $count += $bin->count;
        }
        if ($count !== $simulationCount) {
            throw new InvalidArgumentException('Monte Carlo histogram counts must account for every simulation.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'config' => [
                'simulation_count' => $this->config->simulationCount,
                'trades_per_simulation' => $this->tradesPerSimulation,
                'method' => $this->config->method,
                'initial_capital' => $this->config->initialCapital,
                'random_seed' => $this->config->randomSeed,
                'risk_model' => $this->config->riskModel,
                'drawdown_threshold' => $this->config->drawdownThreshold,
            ],
            'metrics' => $this->metrics->toArray(),
            'distributions' => [
                'final_equity' => $this->finalEquities->toArray(),
                'maximum_drawdown_percent' => $this->maximumDrawdownsPercent->toArray(),
                'maximum_drawdown_amount' => $this->maximumDrawdownsAmount->toArray(),
            ],
            'histograms' => [
                'final_equity' => $this->finalEquityHistogram
                    ->map(static fn (MonteCarloHistogramBin $bin): array => $bin->toArray())
                    ->toArray(),
                'maximum_drawdown_percent' => $this->maximumDrawdownPercentHistogram
                    ->map(static fn (MonteCarloHistogramBin $bin): array => $bin->toArray())
                    ->toArray(),
            ],
        ];
    }
}
