<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypeBacktests;

use App\Engine\Models\MarketTypeBacktestRun;
use App\Engine\Repositories\MarketTypeBacktestRunRepository;
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
