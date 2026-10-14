<?php

declare(strict_types=1);

namespace App\Features\Engine\Actions;

use App\Features\Engine\Config\ConfiguredPairBacktestInput;
use App\Features\Engine\Config\SpecificPairTestConfig;
use App\Features\Engine\Domain\ConfiguredPairBacktestResult;
use App\Features\Engine\Domain\SpecificPairBacktestDomain;
use App\Features\Engine\Repositories\MarketPairRepository;
use App\Features\Engine\Repositories\TimeframeTypeRepository;

class RunConfiguredPairBacktestAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly SpecificPairBacktestDomain $runBacktest,
    ) {}

    public function execute(ConfiguredPairBacktestInput $input): ConfiguredPairBacktestResult
    {
        $pair = $this->marketPairs->findOrFail($input->marketPairId);
        $timeframe = $this->timeframes->findOrFail($input->timeframeTypeId);

        $backtest = $this->runBacktest->execute(new SpecificPairTestConfig(
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
        ));

        return new ConfiguredPairBacktestResult(
            pair: $pair->base.'/'.$pair->quote,
            timeframe: $timeframe->name,
            from: $input->from,
            to: $input->to,
            backtest: $backtest,
        );
    }
}
