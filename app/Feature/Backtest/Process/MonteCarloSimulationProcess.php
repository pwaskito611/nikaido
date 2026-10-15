<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process;

use App\Feature\Backtest\Config\MonteCarloSimulationConfig;
use App\Feature\Backtest\Data\MonteCarloDrawdownMetrics;
use App\Feature\Backtest\Data\MonteCarloEquityMetrics;
use App\Feature\Backtest\Data\MonteCarloHistogramBin;
use App\Feature\Backtest\Data\MonteCarloSimulationMetrics;
use App\Feature\Backtest\Data\MonteCarloSimulationResult;
use App\Feature\Backtest\Data\MonteCarloSimulationValue;
use App\Feature\Backtest\Data\MonteCarloTrade;
use Exact\Collection\SortedSet;
use Exact\Collection\Vec;
use Exact\Type\Tuple\Pair;
use Gauss\Statistics\Statistics;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class MonteCarloSimulationProcess
{
    /**
     * @param  Vec<MonteCarloTrade>  $historicalTrades
     */
    public function execute(Vec $historicalTrades, MonteCarloSimulationConfig $config): MonteCarloSimulationResult
    {
        if ($historicalTrades->isEmpty()) {
            throw new InvalidArgumentException('Monte Carlo simulation requires at least one closed trade with a numeric P/L.');
        }

        if ($config->riskModel === MonteCarloSimulationConfig::RISK_MODEL_COMPOUNDED_RETURN
            && $historicalTrades->exists(
                static fn (MonteCarloTrade $trade): bool => $trade->capitalAtOpen->exists(
                    static fn (float $capital): bool => $capital <= 0,
                ) || $trade->capitalAtOpen->isNone(),
            )) {
            throw new InvalidArgumentException('Compounded return requires a positive capital_at_open for every closed trade.');
        }

        if ($config->method === MonteCarloSimulationConfig::METHOD_SHUFFLE
            && $config->tradesPerSimulation !== null
            && $config->tradesPerSimulation !== $historicalTrades->count()) {
            throw new InvalidArgumentException('Shuffle simulations must use all historical trades exactly once.');
        }

        $tradesPerSimulation = $config->tradesPerSimulation ?? $historicalTrades->count();
        if ($config->simulationCount * $tradesPerSimulation > MonteCarloSimulationConfig::MAX_SIMULATED_TRADES) {
            throw new InvalidArgumentException('The requested simulation workload exceeds the supported limit.');
        }

        $randomizer = new Randomizer(
            new Mt19937($config->randomSeed ?? random_int(0, 2147483647)),
        );
        $finalEquities = Vec::empty();
        $drawdownsPercent = Vec::empty();
        $drawdownsAmount = Vec::empty();

        for ($simulation = 0; $simulation < $config->simulationCount; $simulation++) {
            $sample = $this->sampleTrades($historicalTrades, $tradesPerSimulation, $config->method, $randomizer);
            $curve = $this->runEquityCurve($sample, $config);
            $finalEquities = $finalEquities->append($curve->first());
            $drawdownsPercent = $drawdownsPercent->append($curve->second()->first());
            $drawdownsAmount = $drawdownsAmount->append($curve->second()->second());
        }

        $sortedFinalEquities = $this->sortedValues($finalEquities);
        $sortedDrawdownsPercent = $this->sortedValues($drawdownsPercent);
        $sortedDrawdownsAmount = $this->sortedValues($drawdownsAmount);
        $lossCount = $finalEquities->filter(
            static fn (float $equity): bool => $equity < $config->initialCapital,
        )->count();
        $drawdownCount = $drawdownsPercent->filter(
            static fn (float $drawdown): bool => $drawdown >= $config->drawdownThreshold,
        )->count();

        return new MonteCarloSimulationResult(
            config: $config,
            tradesPerSimulation: $tradesPerSimulation,
            metrics: new MonteCarloSimulationMetrics(
                finalEquity: new MonteCarloEquityMetrics(
                    median: $this->quantile($sortedFinalEquities, 0.5),
                    mean: (float) Statistics::mean($sortedFinalEquities->toArray(), precision: false)->value(),
                    p5: $this->quantile($sortedFinalEquities, 0.05),
                    p25: $this->quantile($sortedFinalEquities, 0.25),
                    p75: $this->quantile($sortedFinalEquities, 0.75),
                    p95: $this->quantile($sortedFinalEquities, 0.95),
                ),
                maximumDrawdownPercent: new MonteCarloDrawdownMetrics(
                    median: $this->quantile($sortedDrawdownsPercent, 0.5),
                    p95: $this->quantile($sortedDrawdownsPercent, 0.95),
                    worst: $sortedDrawdownsPercent->last(),
                ),
                maximumDrawdownAmount: new MonteCarloDrawdownMetrics(
                    median: $this->quantile($sortedDrawdownsAmount, 0.5),
                    p95: $this->quantile($sortedDrawdownsAmount, 0.95),
                    worst: $sortedDrawdownsAmount->last(),
                ),
                probabilityOfLoss: $lossCount / $config->simulationCount * 100,
                probabilityOfDrawdown: $drawdownCount / $config->simulationCount * 100,
            ),
            finalEquities: $sortedFinalEquities,
            maximumDrawdownsPercent: $sortedDrawdownsPercent,
            maximumDrawdownsAmount: $sortedDrawdownsAmount,
            finalEquityHistogram: $this->histogram($sortedFinalEquities),
            maximumDrawdownPercentHistogram: $this->histogram($sortedDrawdownsPercent),
        );
    }

    /**
     * @param  Vec<MonteCarloTrade>  $trades
     * @return Vec<MonteCarloTrade>
     */
    private function sampleTrades(
        Vec $trades,
        int $tradesPerSimulation,
        string $method,
        Randomizer $randomizer,
    ): Vec {
        if ($method === MonteCarloSimulationConfig::METHOD_SHUFFLE) {
            return Vec::fromArray($randomizer->shuffleArray($trades->toArray()));
        }

        $sample = Vec::empty();
        $populationSize = $trades->count();
        for ($index = 0; $index < $tradesPerSimulation; $index++) {
            $sample = $sample->append($trades->at($randomizer->getInt(0, $populationSize - 1)));
        }

        return $sample;
    }

    /**
     * @param  Vec<MonteCarloTrade>  $trades
     * @return Pair<float, Pair<float, float>>
     */
    private function runEquityCurve(Vec $trades, MonteCarloSimulationConfig $config): Pair
    {
        $equity = $config->initialCapital;
        $peakEquity = $config->initialCapital;
        $maximumDrawdownAmount = 0.0;
        $maximumDrawdownPercent = 0.0;

        foreach ($trades as $trade) {
            $nextEquity = $config->riskModel === MonteCarloSimulationConfig::RISK_MODEL_FIXED_MONETARY_PL
                ? $equity + $trade->profitLoss
                : $equity * (1 + $trade->profitLoss / $trade->capitalAtOpen->getOrElse(0.0));
            if (! is_finite($nextEquity)) {
                throw new InvalidArgumentException('Equity overflowed during a simulation.');
            }

            if ($nextEquity <= 0) {
                $equity = 0.0;
                $maximumDrawdownAmount = max($maximumDrawdownAmount, $peakEquity);
                $maximumDrawdownPercent = max($maximumDrawdownPercent, 100.0);

                break;
            }

            $equity = $nextEquity;
            $peakEquity = max($peakEquity, $equity);
            $drawdownAmount = $peakEquity - $equity;
            $drawdownPercent = $peakEquity > 0 ? $drawdownAmount / $peakEquity * 100 : 100.0;
            $maximumDrawdownAmount = max($maximumDrawdownAmount, $drawdownAmount);
            $maximumDrawdownPercent = max($maximumDrawdownPercent, $drawdownPercent);
        }

        return Pair::of($equity, Pair::of($maximumDrawdownPercent, $maximumDrawdownAmount));
    }

    /**
     * @param  Vec<float>  $values
     * @return Vec<float>
     */
    private function sortedValues(Vec $values): Vec
    {
        $sequencedValues = Vec::empty();
        foreach ($values as $sequence => $value) {
            $sequencedValues = $sequencedValues->append(new MonteCarloSimulationValue($sequence, $value));
        }

        return Vec::fromIterable(SortedSet::fromIterable(
            $sequencedValues,
            static fn (MonteCarloSimulationValue $left, MonteCarloSimulationValue $right): int => ($left->value <=> $right->value)
                ?: ($left->sequence <=> $right->sequence),
        ))->map(static fn (MonteCarloSimulationValue $value): float => $value->value);
    }

    /**
     * @param  Vec<float>  $values
     */
    private function quantile(Vec $values, float $quantile): float
    {
        return (float) Statistics::quantile($values->toArray(), $quantile, precision: false)->value();
    }

    /**
     * @param  Vec<float>  $values
     * @return Vec<MonteCarloHistogramBin>
     */
    private function histogram(Vec $values): Vec
    {
        $minimum = $values->first();
        $maximum = $values->last();
        $range = $maximum - $minimum;
        $bins = Vec::empty();

        for ($index = 0; $index < 10; $index++) {
            $from = $minimum + $range * $index / 10;
            $to = $minimum + $range * ($index + 1) / 10;
            $count = $values->filter(
                static function (float $value) use ($minimum, $maximum, $range, $index): bool {
                    $binIndex = $maximum === $minimum
                        ? 0
                        : min((int) floor(($value - $minimum) / $range * 10), 9);

                    return $binIndex === $index;
                },
            )->count();
            $bins = $bins->append(new MonteCarloHistogramBin($from, $to, $count));
        }

        return $bins;
    }
}
