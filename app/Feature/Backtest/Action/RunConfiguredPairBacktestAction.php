<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\ConfiguredPairBacktestInput;
use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\ConfiguredPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;

class RunConfiguredPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly SpecificPairBacktestProcess $runBacktest,
    ) {}

    public function execute(ConfiguredPairBacktestInput $input): ConfiguredPairBacktestResult
    {
        $pair = $this->marketPairs->findOrFail($input->marketPairId);
        $timeframe = $this->timeframes->findOrFail($input->timeframeTypeId);

        $config = new SpecificPairTestConfig(
            base: $pair->base,
            quote: $pair->quote,
            from: $input->from,
            to: $input->to,
            activeFrom: $input->activeSchedule,
            marketPairId: $pair->id,
            timeframeTypeId: $input->timeframeTypeId,
            initialCapital: $input->initialCapital,
            excludedDays: $input->excludedDays,
            slippage: $input->slippage,
            partialFill: $input->partialFill,
        );
        $marketData = $this->marketPairs->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
            $config->timeframeTypeId,
            $config->marketPairId,
        );
        $backtest = $this->runBacktest->execute($config, $marketData);

        return new ConfiguredPairBacktestResult(
            pair: $pair->base.'/'.$pair->quote,
            timeframe: $timeframe->name,
            from: $input->from,
            to: $input->to,
            backtest: $backtest,
        );
    }
}
