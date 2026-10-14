<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypeBacktests;

use App\Features\Engine\Models\MarketTypeBacktestPairRun;
use App\Features\Engine\Models\MarketTypeBacktestRun;
use App\Features\Engine\Repositories\MarketTypeBacktestRunRepository;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoadMarketTypeOverallResultAction
{
    public function __construct(
        private readonly MarketTypeBacktestRunRepository $runs,
    ) {}

    /**
     * @return array{run: MarketTypeBacktestRun, pairRuns: Collection<int, MarketTypeBacktestPairRun>}
     */
    public function execute(int $runId): array
    {
        $run = $this->runs->findOrFail($runId);
        if (! in_array($run->status, ['completed', 'completed_with_failures'], true)) {
            throw new HttpException(409);
        }

        $pairRuns = $this->runs->successfulPairRuns($runId);
        if ($pairRuns->isEmpty()) {
            throw new HttpException(409);
        }

        return ['run' => $run, 'pairRuns' => $pairRuns];
    }
}
