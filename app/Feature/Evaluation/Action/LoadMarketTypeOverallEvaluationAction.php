<?php

declare(strict_types=1);

namespace App\Feature\Evaluation\Action;

use App\Feature\Backtest\Action\MarketTypeBacktests\CombineMarketTypeBacktestResultsAction;
use App\Feature\Backtest\Action\MarketTypeBacktests\LoadMarketTypeOverallResultAction;

class LoadMarketTypeOverallEvaluationAction
{
    public function __construct(
        private readonly LoadMarketTypeOverallResultAction $loadOverallResult,
        private readonly CombineMarketTypeBacktestResultsAction $combineResults,
    ) {}

    /**
     * @return array{result: array<string, mixed>, successfulPairCount: int}
     */
    public function execute(int $runId): array
    {
        $overall = $this->loadOverallResult->execute($runId);

        return [
            'result' => $this->combineResults->execute($overall['run'], $overall['pairRuns']),
            'successfulPairCount' => $overall['pairRuns']->count(),
        ];
    }
}
