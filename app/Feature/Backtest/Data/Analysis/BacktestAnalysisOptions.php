<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Data\Analysis;

use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use Exact\Type\Option\Option;
use UnexpectedValueException;

final readonly class BacktestAnalysisOptions
{
    /**
     * @param  Option<float>  $stressParameter
     * @param  Option<MonteCarloSimulationConfig>  $monteCarloConfig
     * @param  Option<int>  $clusterCount
     */
    private function __construct(
        public Option $stressParameter,
        public Option $monteCarloConfig,
        public Option $clusterCount,
        public int $selectedCluster,
    ) {}

    /**
     * Convert validated HTTP query data into typed analysis options.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidatedQuery(
        array $validated,
        ?int $clusterCount,
        int $selectedCluster,
    ): self {
        if ($clusterCount !== null && ($clusterCount < 2 || $clusterCount > 10)) {
            throw new UnexpectedValueException('Backtest cluster count must be between 2 and 10.');
        }
        if ($selectedCluster < 1 || ($clusterCount !== null && $selectedCluster > $clusterCount)) {
            throw new UnexpectedValueException('Selected backtest cluster is outside the available range.');
        }

        $stressParameter = self::optionalFiniteNumber($validated, 'stress_x');
        $monteCarloConfig = Option::none();
        if (filter_var($validated['monte_carlo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $monteCarloConfig = Option::some(new MonteCarloSimulationConfig(
                simulationCount: self::requiredInteger($validated, 'simulation_count'),
                tradesPerSimulation: self::optionalInteger($validated, 'trades_per_simulation'),
                method: self::requiredString($validated, 'method'),
                initialCapital: self::requiredFiniteNumber($validated, 'mc_initial_capital'),
                randomSeed: self::optionalInteger($validated, 'random_seed'),
                riskModel: self::requiredString($validated, 'risk_model'),
                drawdownThreshold: self::requiredFiniteNumber($validated, 'drawdown_threshold'),
            ));
        }

        return new self(
            stressParameter: $stressParameter,
            monteCarloConfig: $monteCarloConfig,
            clusterCount: Option::from($clusterCount),
            selectedCluster: $selectedCluster,
        );
    }

    /** @param array<string, mixed> $values */
    private static function optionalFiniteNumber(array $values, string $field): Option
    {
        if (! array_key_exists($field, $values) || $values[$field] === null) {
            return Option::none();
        }
        if (! is_numeric($values[$field]) || ! is_finite((float) $values[$field])) {
            throw new UnexpectedValueException("Backtest analysis {$field} must be a finite number.");
        }

        return Option::some((float) $values[$field]);
    }

    /** @param array<string, mixed> $values */
    private static function requiredFiniteNumber(array $values, string $field): float
    {
        return self::optionalFiniteNumber($values, $field)->getOrElseThrow(
            new UnexpectedValueException("Backtest analysis {$field} is required."),
        );
    }

    /** @param array<string, mixed> $values */
    private static function requiredInteger(array $values, string $field): int
    {
        $value = $values[$field] ?? null;
        if (! is_int($value) && (! is_string($value) || filter_var($value, FILTER_VALIDATE_INT) === false)) {
            throw new UnexpectedValueException("Backtest analysis {$field} must be an integer.");
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $values */
    private static function optionalInteger(array $values, string $field): ?int
    {
        if (! array_key_exists($field, $values) || $values[$field] === null) {
            return null;
        }

        return self::requiredInteger($values, $field);
    }

    /** @param array<string, mixed> $values */
    private static function requiredString(array $values, string $field): string
    {
        $value = $values[$field] ?? null;
        if (! is_string($value)) {
            throw new UnexpectedValueException("Backtest analysis {$field} must be a string.");
        }

        return $value;
    }
}
