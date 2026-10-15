<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action;

use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Process\SpecificPairBacktestProcess;
use App\Feature\MarketPairs\Repository\MarketPairRepository;

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

        return $this->backtest->execute($config, $marketData);
    }
}
