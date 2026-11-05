<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\CashFlows\Action\LoadMarketPairCashFlowsForBacktestAction;
use App\Feature\Fundamentals\Action\LoadFundamentalDataForBacktestAction;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Illuminate\Validation\ValidationException;
use Nikaido\Strategy\Models\CashFlowDataCollection;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly SpecificPairBacktestProcess $backtest,
        private readonly LoadFundamentalDataForBacktestAction $loadFundamentals,
        private readonly LoadMarketPairCashFlowsForBacktestAction $loadCashFlows,
    ) {}

    public function execute(SpecificPairTestConfig $config): SpecificPairBacktestResult
    {
        $marketData = $this->marketPairs->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
            $config->timeframeTypeId,
            $config->marketPairId,
        );

        $hasCandlesInRange = false;
        foreach ($marketData->candles as $candle) {
            if ($candle->price_date < $config->from || $candle->price_date > $config->to) {
                continue;
            }

            $hasCandlesInRange = true;
            if ($config->excludedDays->contains((int) $candle->price_date->format('N'))) {
                continue;
            }
            if (! $config->activeSchedule->includes($candle->price_date)) {
                continue;
            }

            $fundamentals = $this->loadFundamentals->execute(
                (int) $marketData->marketPairId,
                $config->from,
                $config->to,
            );
            $cashFlows = $config->includeCashFlows
                ? $this->loadCashFlows->execute((int) $marketData->marketPairId, $config->from, $config->to)
                : new CashFlowDataCollection;

            return $this->backtest->execute($config, $marketData, $fundamentals, $cashFlows);
        }

        throw ValidationException::withMessages([
            'from' => $hasCandlesInRange
                ? 'No candles fall within the selected active hours and days.'
                : 'No market price candles were found for this pair, timeframe, and date range.',
        ]);
    }
}
