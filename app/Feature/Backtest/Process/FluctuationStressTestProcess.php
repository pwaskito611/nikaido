<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process;

use App\Feature\Backtest\Data\FluctuationStressTestResult;
use App\Feature\Backtest\Data\StressTestInput;
use App\Feature\Backtest\Data\StressTestMetric;
use App\Feature\Backtest\Data\StressTestMetricName;
use App\Feature\Backtest\Data\StressTestMetrics;
use App\Feature\Backtest\Data\StressTestScenarioOrder;
use App\Feature\Backtest\Data\StressTestTrade;
use App\Platform\Strategy\Enums\StrategySignal;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use InvalidArgumentException;

class FluctuationStressTestProcess
{
    public function execute(StressTestInput $input, float $x): FluctuationStressTestResult
    {
        if (! is_finite($x) || $x < 0) {
            throw new InvalidArgumentException('Stress parameter x must be a non-negative finite number.');
        }

        $scenarioOrders = Vec::empty();
        $capital = $input->initialCapital;
        foreach ($input->trades as $trade) {
            $scenarioOrder = $this->scenarioOrder($trade, $input, $x);
            if ($scenarioOrder->profitLoss->isSome()) {
                $capital += $scenarioOrder->profitLoss->getOrElse(0.0);
                $scenarioOrder = new StressTestScenarioOrder(
                    baselineTrade: $scenarioOrder->baselineTrade,
                    orderPayload: $scenarioOrder->orderPayload,
                    closeDate: $scenarioOrder->closeDate,
                    closeReason: $scenarioOrder->closeReason,
                    closePrice: $scenarioOrder->closePrice,
                    profitLoss: $scenarioOrder->profitLoss,
                    capitalAfterClose: Option::some($capital),
                    stressExtreme: $scenarioOrder->stressExtreme,
                    stressExitDelta: $scenarioOrder->stressExitDelta,
                    stressExcursion: $scenarioOrder->stressExcursion,
                    stressDistance: $scenarioOrder->stressDistance,
                );
            }
            $scenarioOrders = $scenarioOrders->append($scenarioOrder);
        }

        return new FluctuationStressTestResult(
            baseline: $this->metrics($input->trades, $input->initialCapital),
            scenario: $this->metrics(
                $scenarioOrders->map(static fn (StressTestScenarioOrder $order): StressTestTrade => $order->toTrade()),
                $input->initialCapital,
            ),
            orders: $scenarioOrders,
            x: $x,
        );
    }

    private function scenarioOrder(StressTestTrade $trade, StressTestInput $input, float $x): StressTestScenarioOrder
    {
        $scenarioReason = $trade->closeReason;
        $scenarioCloseDate = $trade->closeDate;
        $scenarioClosePrice = $trade->closePrice;
        $stressExtreme = Option::none();
        $stressExitDelta = Option::none();
        $stressExcursion = Option::none();
        $stressDistance = Option::none();
        $isBaselineProfit = $trade->profitLoss->getOrElse(0.0) > 0.0;
        $extreme = $isBaselineProfit
            ? ($trade->adverseExtreme->isSome() ? $trade->adverseExtreme : $trade->closestToStopLoss)
            : ($trade->favorableExtreme->isSome() ? $trade->favorableExtreme : $trade->closestToTakeProfit);

        if ($x > 0 && $trade->closeDate->isSome() && $trade->closePrice->isSome() && $extreme->isSome()) {
            $selectedExtreme = $extreme->getOrElseThrow(
                new InvalidArgumentException('A stress extreme was expected after presence was checked.'),
            );
            $excursion = abs($trade->entryPrice - $selectedExtreme->price);
            $stressedDistance = $x * $excursion;
            if (! is_finite($stressedDistance)) {
                throw new InvalidArgumentException('Stress parameter x produces a non-finite price excursion.');
            }

            $baselineClosePrice = $trade->closePrice->getOrElse(0.0);
            $signalDirection = $trade->signal === StrategySignal::Long ? 1.0 : -1.0;
            $stressedPrice = $baselineClosePrice - $signalDirection * $stressedDistance;
            $stopHit = $signalDirection * ($stressedPrice - $trade->stopLoss) <= 0;
            $slippage = $this->slippage($input, $trade);
            $scenarioClosePrice = Option::some(
                $stopHit
                    ? $trade->stopLoss - $signalDirection * $slippage
                    : $stressedPrice,
            );
            $scenarioReason = Option::some($stopHit ? 'stop_loss' : 'stress_test');
            $scenarioCloseDate = $selectedExtreme->date->isSome() ? $selectedExtreme->date : $trade->closeDate;
            $stressExitDelta = Option::some($scenarioClosePrice->getOrElse(0.0) - $baselineClosePrice);
            $stressExtreme = Option::some($selectedExtreme);
            $stressExcursion = Option::some($excursion);
            $stressDistance = Option::some($stressedDistance);
        }

        $profitLoss = Option::none();
        if ($scenarioClosePrice->isSome() && $trade->profitLoss->isSome()) {
            $baselineProfitLoss = $trade->profitLoss->getOrElse(0.0);
            $exitDelta = $scenarioClosePrice->getOrElse(0.0) - $trade->closePrice->getOrElse(0.0);
            $signalDirection = $trade->signal === StrategySignal::Long ? 1.0 : -1.0;
            $profitLoss = Option::some(
                $baselineProfitLoss
                + $signalDirection * $exitDelta * $trade->filledPositionSize() * $trade->pnlMultiplier,
            );
        }

        return new StressTestScenarioOrder(
            baselineTrade: $trade,
            orderPayload: $trade->orderPayload,
            closeDate: $scenarioCloseDate,
            closeReason: $scenarioReason,
            closePrice: $scenarioClosePrice,
            profitLoss: $profitLoss,
            capitalAfterClose: Option::none(),
            stressExtreme: $stressExtreme,
            stressExitDelta: $stressExitDelta,
            stressExcursion: $stressExcursion,
            stressDistance: $stressDistance,
        );
    }

    private function slippage(StressTestInput $input, StressTestTrade $trade): float
    {
        if (! $input->slippage->enabled) {
            return 0.0;
        }

        $quote = $trade->quote->getOrElse($input->quote->getOrElse(''));
        $pipSize = $input->slippage->pipSize ?? (strtoupper($quote) === 'JPY' ? 0.01 : 0.0001);

        return $input->slippage->pips * $pipSize;
    }

    /**
     * @param  Vec<StressTestTrade>  $trades
     */
    private function metrics(Vec $trades, float $initialCapital): StressTestMetrics
    {
        $closedOrders = $trades->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeDate->isSome() && $trade->profitLoss->isSome(),
        );
        $netProfit = $closedOrders->foldLeft(
            0.0,
            static fn (float $total, StressTestTrade $trade): float => $total + $trade->profitLoss->getOrElse(0.0),
        );
        $grossProfit = $closedOrders->foldLeft(
            0.0,
            static fn (float $total, StressTestTrade $trade): float => $total + max($trade->profitLoss->getOrElse(0.0), 0),
        );
        $grossLoss = abs($closedOrders->foldLeft(
            0.0,
            static fn (float $total, StressTestTrade $trade): float => $total + min($trade->profitLoss->getOrElse(0.0), 0),
        ));
        $stopLossCount = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'stop_loss',
        )->count();
        $takeProfitCount = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'take_profit',
        )->count();
        $stressTestCount = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'stress_test',
        )->count();
        $profitableTrades = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->profitLoss->getOrElse(0.0) > 0,
        );
        $losingTrades = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->profitLoss->getOrElse(0.0) < 0,
        );
        $breakevenTrades = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->profitLoss->getOrElse(0.0) === 0.0,
        );
        $profitableStressExits = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'stress_test'
                && $trade->profitLoss->getOrElse(0.0) > 0,
        );
        $losingStressExits = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'stress_test'
                && $trade->profitLoss->getOrElse(0.0) < 0,
        );
        $breakevenStressExits = $closedOrders->filter(
            static fn (StressTestTrade $trade): bool => $trade->closeReason->getOrElse('') === 'stress_test'
                && $trade->profitLoss->getOrElse(0.0) === 0.0,
        );
        $capital = $initialCapital;
        $peakCapital = $initialCapital;
        $maximumDrawdown = 0.0;
        foreach ($closedOrders as $trade) {
            $capital += $trade->profitLoss->getOrElse(0.0);
            $peakCapital = max($peakCapital, $capital);
            $maximumDrawdown = max($maximumDrawdown, $peakCapital - $capital);
        }

        $tradeCount = $closedOrders->count();
        $otherExitCount = $tradeCount - $stopLossCount - $takeProfitCount - $stressTestCount;
        $metrics = Vec::empty();
        $metrics = $this->metric($metrics, StressTestMetricName::NetProfit, Option::some($netProfit));
        $metrics = $this->metric($metrics, StressTestMetricName::Expectancy, Option::some($tradeCount > 0 ? $netProfit / $tradeCount : 0.0));
        $metrics = $this->metric(
            $metrics,
            StressTestMetricName::ProfitFactor,
            $grossLoss > 0 ? Option::some($grossProfit / $grossLoss) : Option::when($grossProfit === 0.0, 0.0),
        );
        $metrics = $this->metric($metrics, StressTestMetricName::MaxDrawdown, Option::some($maximumDrawdown));
        $metrics = $this->metric($metrics, StressTestMetricName::TradeCount, Option::some($tradeCount));
        $metrics = $this->metric($metrics, StressTestMetricName::StopLossCount, Option::some($stopLossCount));
        $metrics = $this->metric($metrics, StressTestMetricName::TakeProfitCount, Option::some($takeProfitCount));
        $metrics = $this->metric($metrics, StressTestMetricName::StressTestCount, Option::some($stressTestCount));
        $metrics = $this->metric($metrics, StressTestMetricName::OtherExitCount, Option::some($otherExitCount));
        $metrics = $this->metric($metrics, StressTestMetricName::ProfitableTradeCount, Option::some($profitableTrades->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::LosingTradeCount, Option::some($losingTrades->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::BreakevenTradeCount, Option::some($breakevenTrades->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::ProfitableStressExitCount, Option::some($profitableStressExits->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::LosingStressExitCount, Option::some($losingStressExits->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::BreakevenStressExitCount, Option::some($breakevenStressExits->count()));
        $metrics = $this->metric($metrics, StressTestMetricName::StopLossFrequency, Option::some($this->frequency($stopLossCount, $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::TakeProfitFrequency, Option::some($this->frequency($takeProfitCount, $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::StressTestFrequency, Option::some($this->frequency($stressTestCount, $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::OtherExitFrequency, Option::some($this->frequency($otherExitCount, $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::ProfitableTradeFrequency, Option::some($this->frequency($profitableTrades->count(), $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::LosingTradeFrequency, Option::some($this->frequency($losingTrades->count(), $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::BreakevenTradeFrequency, Option::some($this->frequency($breakevenTrades->count(), $tradeCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::ProfitableStressExitFrequency, Option::some($this->frequency($profitableStressExits->count(), $stressTestCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::LosingStressExitFrequency, Option::some($this->frequency($losingStressExits->count(), $stressTestCount)));
        $metrics = $this->metric($metrics, StressTestMetricName::BreakevenStressExitFrequency, Option::some($this->frequency($breakevenStressExits->count(), $stressTestCount)));

        return new StressTestMetrics($metrics);
    }

    /** @param Vec<StressTestMetric> $metrics */
    private function metric(Vec $metrics, StressTestMetricName $name, Option $value): Vec
    {
        return $metrics->append(new StressTestMetric($name, $value));
    }

    private function frequency(int $count, int $denominator): float
    {
        return $denominator > 0 ? $count / $denominator * 100 : 0.0;
    }
}
