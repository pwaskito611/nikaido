<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypeBacktests;

use App\Features\Engine\Models\MarketTypeBacktestPairRun;
use App\Features\Engine\Repositories\MarketTypeBacktestRunRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LoadMarketTypePairResultAction
{
    public function __construct(
        private readonly MarketTypeBacktestRunRepository $runs,
    ) {}

    public function execute(int $runId, int $pairRunId): MarketTypeBacktestPairRun
    {
        $pairRun = $this->runs->findPairRunOrFail($runId, $pairRunId);
        if ($pairRun->status !== 'succeeded' || ! is_array($pairRun->result)) {
            throw new NotFoundHttpException;
        }

        return $pairRun;
    }
}
