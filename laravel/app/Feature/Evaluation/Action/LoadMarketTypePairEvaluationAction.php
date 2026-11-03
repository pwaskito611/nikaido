<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Action;

use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypePairResultAction;

class LoadMarketTypePairEvaluationAction
{
    public function __construct(
        private readonly LoadMarketTypePairResultAction $loadPairResult,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(int $runId, int $pairRunId): array
    {
        return $this->loadPairResult->execute($runId, $pairRunId)->result;
    }
}
