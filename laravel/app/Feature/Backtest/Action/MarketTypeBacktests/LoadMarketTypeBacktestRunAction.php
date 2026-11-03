<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action\MarketTypeBacktests;

use App\Feature\Backtest\Model\MarketTypeBacktestRun;
use App\Feature\Backtest\Repository\MarketTypeBacktestRunRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoadMarketTypeBacktestRunAction
{
    public function __construct(
        private readonly MarketTypeBacktestRunRepository $runs,
    ) {}

    /**
     * @return array{
     *     run: MarketTypeBacktestRun,
     *     pairRuns: LengthAwarePaginator,
     *     counts: array{pending: int, running: int, succeeded: int, failed: int}
     * }
     */
    public function execute(int $runId): array
    {
        return [
            'run' => $this->runs->findOrFail($runId),
            'pairRuns' => $this->runs->paginatePairRuns($runId, 25),
            'counts' => $this->runs->pairRunCounts($runId),
        ];
    }
}
