<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Process;

use App\Feature\Evaluation\Data\FluctuationStressTestResult;
use App\Feature\Evaluation\Data\StressTestInput;
use App\Feature\Evaluation\Data\StressTestMetric;
use App\Feature\Evaluation\Data\StressTestMetricName;
use App\Feature\Evaluation\Data\StressTestMetrics;
use App\Feature\Evaluation\Data\StressTestScenarioOrder;
use App\Feature\Evaluation\Data\StressTestTrade;
use Exact\Collection\Vec;
use Exact\Query\Query;
use Exact\Type\Option\Option;
use InvalidArgumentException;
use Nikaido\Strategy\Enums\StrategySignal;

class FluctuationStressTestProcess
{
    public function execute(StressTestInput $input, float $x): FluctuationStressTestResult
    {
        if (! is_finite($x) || $x < 0) {
            throw new InvalidArgumentException('Stress parameter x must be a non-negative finite number.');
        }

        $scenarioState = Query::from($input->trades)->fold(
            [
                'capital' => $input->initialCapital,
                'orders' => Vec::empty(),
            ],
            function (array $state, StressTestTrade $trade) use ($input, $x): array {
                $scenarioOrder = $this->scenarioOrder($trade, $input, $x);
                $nextCapital = $state['capital'] + $scenarioOrder->profitLoss->getOrElse(0.0);
                $scenarioOrder = $scenarioOrder->profitLoss->map(
                    static fn (float $_): StressTestScenarioOrder => new StressTestScenarioOrder(
                        baselineTrade: $scenarioOrder->baselineTrade,
                        orderPayload: $scenarioOrder->orderPayload,
                        closeDate: $scenarioOrder->closeDate,
                        closeReason: $scenarioOrder->closeReason,
                        closePrice: $scenarioOrder->closePrice,
                        profitLoss: $scenarioOrder->profitLoss,
                        capitalAfterClose: Option::some($nextCapital),
                        stressExtreme: $scenarioOrder->stressExtreme,
                        stressExitDelta: $scenarioOrder->stressExitDelta,
                        stressExcursion: $scenarioOrder->stressExcursion,
                        stressDistance: $scenarioOrder->stressDistance,
                    ),
                )->getOrElse($scenarioOrder);

                return [
                    'capital' => $nextCapital,
                    'orders' => $state['orders']->append($scenarioOrder),
                ];
            },
        );
        $scenarioOrders = $scenarioState['orders'];

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
        $closedOrders = Query::from($trades)
            ->filter(static fn (StressTestTrade $trade): bool => $trade->closeDate->isSome()
                && $trade->profitLoss->isSome())
            ->execute();
        $tradeCount = $closedOrders->count();
        $netProfit = Query::from($closedOrders)->sum(
            static fn (StressTestTrade $trade): float => $trade->profitLoss->getOrElse(0.0),
        );
        $grossProfit = Query::from($closedOrders)->sum(
            static fn (StressTestTrade $trade): float => max($trade->profitLoss->getOrElse(0.0), 0.0),
        );
        $grossLoss = Query::from($closedOrders)->sum(
            static fn (StressTestTrade $trade): float => abs(min($trade->profitLoss->getOrElse(0.0), 0.0)),
        );
        $counts = Query::from($closedOrders)
            ->flatMap(static function (StressTestTrade $trade): Vec {
                $reason = $trade->closeReason->getOrElse('');
                $profitLoss = $trade->profitLoss->getOrElse(0.0);
                $outcome = match (true) {
                    $profitLoss > 0 => 'profitable',
                    $profitLoss < 0 => 'losing',
                    default => 'breakeven',
                };
                $reasonBucket = in_array($reason, ['stop_loss', 'take_profit', 'stress_test'], true)
                    ? $reason
                    : 'other_exit';

                return Vec::of(
                    'reason:'.$reasonBucket,
                    'outcome:'.$outcome,
                    $reason === 'stress_test' ? 'stress_outcome:'.$outcome : null,
                )->filter(static fn (?string $bucket): bool => $bucket !== null);
            })
            ->countBy(static fn (string $bucket): string => $bucket);
        $stopLossCount = $counts->getOrElse('reason:stop_loss', 0);
        $takeProfitCount = $counts->getOrElse('reason:take_profit', 0);
        $stressTestCount = $counts->getOrElse('reason:stress_test', 0);
        $otherExitCount = $counts->getOrElse('reason:other_exit', 0);
        $profitableTradeCount = $counts->getOrElse('outcome:profitable', 0);
        $losingTradeCount = $counts->getOrElse('outcome:losing', 0);
        $breakevenTradeCount = $counts->getOrElse('outcome:breakeven', 0);
        $profitableStressExitCount = $counts->getOrElse('stress_outcome:profitable', 0);
        $losingStressExitCount = $counts->getOrElse('stress_outcome:losing', 0);
        $breakevenStressExitCount = $counts->getOrElse('stress_outcome:breakeven', 0);
        $drawdownState = Query::from($closedOrders)->fold(
            [
                'capital' => $initialCapital,
                'peakCapital' => $initialCapital,
                'maximumDrawdown' => 0.0,
            ],
            static function (array $state, StressTestTrade $trade): array {
                $capital = $state['capital'] + $trade->profitLoss->getOrElse(0.0);
                $peakCapital = max($state['peakCapital'], $capital);

                return [
                    'capital' => $capital,
                    'peakCapital' => $peakCapital,
                    'maximumDrawdown' => max($state['maximumDrawdown'], $peakCapital - $capital),
                ];
            },
        );
        $metrics = Vec::of(
            [StressTestMetricName::NetProfit, Option::some($netProfit)],
            [StressTestMetricName::Expectancy, Option::some($tradeCount > 0 ? $netProfit / $tradeCount : 0.0)],
            [
                StressTestMetricName::ProfitFactor,
                $grossLoss > 0 ? Option::some($grossProfit / $grossLoss) : Option::when($grossProfit === 0.0, 0.0),
            ],
            [StressTestMetricName::MaxDrawdown, Option::some($drawdownState['maximumDrawdown'])],
            [StressTestMetricName::TradeCount, Option::some($tradeCount)],
            [StressTestMetricName::StopLossCount, Option::some($stopLossCount)],
            [StressTestMetricName::TakeProfitCount, Option::some($takeProfitCount)],
            [StressTestMetricName::StressTestCount, Option::some($stressTestCount)],
            [StressTestMetricName::OtherExitCount, Option::some($otherExitCount)],
            [StressTestMetricName::ProfitableTradeCount, Option::some($profitableTradeCount)],
            [StressTestMetricName::LosingTradeCount, Option::some($losingTradeCount)],
            [StressTestMetricName::BreakevenTradeCount, Option::some($breakevenTradeCount)],
            [StressTestMetricName::ProfitableStressExitCount, Option::some($profitableStressExitCount)],
            [StressTestMetricName::LosingStressExitCount, Option::some($losingStressExitCount)],
            [StressTestMetricName::BreakevenStressExitCount, Option::some($breakevenStressExitCount)],
            [StressTestMetricName::StopLossFrequency, Option::some($this->frequency($stopLossCount, $tradeCount))],
            [StressTestMetricName::TakeProfitFrequency, Option::some($this->frequency($takeProfitCount, $tradeCount))],
            [StressTestMetricName::StressTestFrequency, Option::some($this->frequency($stressTestCount, $tradeCount))],
            [StressTestMetricName::OtherExitFrequency, Option::some($this->frequency($otherExitCount, $tradeCount))],
            [StressTestMetricName::ProfitableTradeFrequency, Option::some($this->frequency($profitableTradeCount, $tradeCount))],
            [StressTestMetricName::LosingTradeFrequency, Option::some($this->frequency($losingTradeCount, $tradeCount))],
            [StressTestMetricName::BreakevenTradeFrequency, Option::some($this->frequency($breakevenTradeCount, $tradeCount))],
            [StressTestMetricName::ProfitableStressExitFrequency, Option::some($this->frequency($profitableStressExitCount, $stressTestCount))],
            [StressTestMetricName::LosingStressExitFrequency, Option::some($this->frequency($losingStressExitCount, $stressTestCount))],
            [StressTestMetricName::BreakevenStressExitFrequency, Option::some($this->frequency($breakevenStressExitCount, $stressTestCount))],
        )->map(
            static fn (array $definition): StressTestMetric => new StressTestMetric($definition[0], $definition[1]),
        );

        return new StressTestMetrics($metrics);
    }

    private function frequency(int $count, int $denominator): float
    {
        return $denominator > 0 ? $count / $denominator * 100 : 0.0;
    }
}
