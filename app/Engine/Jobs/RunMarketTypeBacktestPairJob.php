<?php

declare(strict_types=1);

namespace App\Engine\Jobs;

use App\Engine\Actions\RecordMarketTypeBacktestPairResultAction;
use App\Engine\Actions\RunConfiguredPairBacktestAction;
use App\Engine\Repositories\MarketTypeBacktestRunRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunMarketTypeBacktestPairJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $pairRunId,
    ) {}

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('market-type-backtest-pair-'.$this->pairRunId))
                ->releaseAfter(10)
                ->expireAfter(75),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(
        RunConfiguredPairBacktestAction $runBacktest,
        RecordMarketTypeBacktestPairResultAction $recordResult,
        MarketTypeBacktestRunRepository $runs,
    ): void {
        $pairRun = $runs->findPairRunWithRunOrFail($this->pairRunId);
        if (in_array($pairRun->status, ['succeeded', 'failed'], true)) {
            return;
        }

        $runs->markPairRunRunning($this->pairRunId, $pairRun->started_at ?? now());

        $configuration = $pairRun->run->configuration;
        $configuration['market_pair_id'] = $pairRun->pair_id;
        unset($configuration['timeframe_name']);
        $recordResult->succeed($pairRun->id, $runBacktest->execute($configuration));
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        app(RecordMarketTypeBacktestPairResultAction::class)->fail($this->pairRunId);
    }
}
