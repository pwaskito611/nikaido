<?php

declare(strict_types=1);

namespace App\Features\Engine\Actions;

use App\Features\Engine\Config\SpecificPairTestConfig;
use App\Features\Engine\Domain\SpecificPairBacktestDomain;
use App\Features\Engine\Domain\SpecificPairBacktestResult;

class RunSpecificPairBacktestAction
{
    public function __construct(
        private readonly SpecificPairBacktestDomain $backtest,
    ) {}

    public function execute(SpecificPairTestConfig $config): SpecificPairBacktestResult
    {
        return $this->backtest->execute($config);
    }
}
