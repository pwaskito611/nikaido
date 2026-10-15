<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Illuminate\Validation\ValidationException;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly SpecificPairBacktestProcess $backtest,
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

            return $this->backtest->execute($config, $marketData);
        }

        throw ValidationException::withMessages([
            'from' => $hasCandlesInRange
                ? 'No candles fall within the selected active hours and days.'
                : 'No market price candles were found for this pair, timeframe, and date range.',
        ]);
    }
}
