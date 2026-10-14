<?php

declare(strict_types=1);

namespace App\Features\Engine\Domain;

use Exact\Type\Option\Option;

final readonly class StressTestScenarioOrder
{
    /**
     * @param  Option<string>  $closeDate
     * @param  Option<string>  $closeReason
     * @param  Option<float>  $closePrice
     * @param  Option<float>  $profitLoss
     * @param  Option<float>  $capitalAfterClose
     * @param  Option<StressTestExtreme>  $stressExtreme
     * @param  Option<float>  $stressExitDelta
     * @param  Option<float>  $stressExcursion
     * @param  Option<float>  $stressDistance
     */
    public function __construct(
        public StressTestTrade $baselineTrade,
        public StressTestOrderPayload $orderPayload,
        public Option $closeDate,
        public Option $closeReason,
        public Option $closePrice,
        public Option $profitLoss,
        public Option $capitalAfterClose,
        public Option $stressExtreme,
        public Option $stressExitDelta,
        public Option $stressExcursion,
        public Option $stressDistance,
    ) {}

    public function toTrade(): StressTestTrade
    {
        return new StressTestTrade(
            signal: $this->baselineTrade->signal,
            entryPrice: $this->baselineTrade->entryPrice,
            stopLoss: $this->baselineTrade->stopLoss,
            positionSize: $this->baselineTrade->positionSize,
            pnlMultiplier: $this->baselineTrade->pnlMultiplier,
            adverseExtreme: $this->baselineTrade->adverseExtreme,
            favorableExtreme: $this->baselineTrade->favorableExtreme,
            closestToStopLoss: $this->baselineTrade->closestToStopLoss,
            closestToTakeProfit: $this->baselineTrade->closestToTakeProfit,
            closeDate: $this->closeDate,
            closeReason: $this->closeReason,
            closePrice: $this->closePrice,
            profitLoss: $this->profitLoss,
            closedPositionSize: $this->baselineTrade->closedPositionSize,
            quote: $this->baselineTrade->quote,
            orderPayload: $this->orderPayload,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $order = $this->orderPayload->toExternal();
        $order['close_reason'] = $this->closeReason->getOrNull();
        $order['close_date'] = $this->closeDate->getOrNull();
        $order['close_price'] = $this->closePrice->getOrNull();
        $order['profit_loss'] = $this->profitLoss->getOrNull();
        $order['capital_after_close'] = $this->capitalAfterClose->getOrNull();

        if ($this->stressExitDelta->isSome()) {
            $order['stress_exit_delta'] = $this->stressExitDelta->getOrNull();
            $order['stress_extreme'] = $this->stressExtreme->map(
                static fn (StressTestExtreme $extreme): array => $extreme->toArray(),
            )->getOrNull();
            $order['stress_excursion'] = $this->stressExcursion->getOrNull();
            $order['stress_distance'] = $this->stressDistance->getOrNull();
        }

        return $order;
    }
}
