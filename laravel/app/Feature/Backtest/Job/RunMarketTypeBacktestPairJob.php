<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Job;

use App\Feature\Backtest\Action\MarketTypeBacktests\RecordMarketTypeBacktestPairResultAction;
use App\Feature\Backtest\Action\RunConfiguredPairBacktestAction;
use App\Feature\Backtest\Config\ConfiguredPairBacktestInput;
use App\Feature\Backtest\Repository\MarketTypeBacktestRunRepository;
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
        unset($configuration['timeframe_name'], $configuration['capital_mode']);
        $recordResult->succeed(
            $pairRun->id,
            $runBacktest->execute(ConfiguredPairBacktestInput::fromArray($configuration))->toPersistedArray(),
        );
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        app(RecordMarketTypeBacktestPairResultAction::class)->fail($this->pairRunId);
    }
}
