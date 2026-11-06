<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\CashFlows\Action\LoadMarketPairCashFlowsForBacktestAction;
use App\Feature\Fundamentals\Action\LoadFundamentalDataForBacktestAction;
use App\Feature\MarketPairs\Data\InvalidCfdContractSpecification;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\News\Repository\NewsRepository;
use Illuminate\Validation\ValidationException;
use Nikaido\Strategy\Models\CashFlowDataCollection;
use Nikaido\Strategy\Models\NewsDataCollection;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly SpecificPairBacktestProcess $backtest,
        private readonly LoadFundamentalDataForBacktestAction $loadFundamentals,
        private readonly LoadMarketPairCashFlowsForBacktestAction $loadCashFlows,
        private readonly ?NewsRepository $news = null,
    ) {}

    public function execute(SpecificPairTestConfig $config): SpecificPairBacktestResult
    {
        try {
            $marketData = $this->marketPairs->findWithPricesBetween(
                $config->base,
                $config->quote,
                $config->from,
                $config->to,
                $config->timeframeTypeId,
                $config->marketPairId,
            );
        } catch (InvalidCfdContractSpecification) {
            throw ValidationException::withMessages([
                'market_pair_id' => 'The selected pair has an incomplete or invalid CFD contract profile. Edit the pair in Market Pairs and complete all CFD contract fields before running this backtest.',
            ]);
        }

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
            $news = $this->news?->forBacktest((int) $marketData->marketPairId, $config->from, $config->to)
                ?? new NewsDataCollection;

            return $this->backtest->execute($config, $marketData, $fundamentals, $cashFlows, $news);
        }

        throw ValidationException::withMessages([
            'from' => $hasCandlesInRange
                ? 'No candles fall within the selected active hours and days.'
                : 'No market price candles were found for this pair, timeframe, and date range.',
        ]);
    }
}
