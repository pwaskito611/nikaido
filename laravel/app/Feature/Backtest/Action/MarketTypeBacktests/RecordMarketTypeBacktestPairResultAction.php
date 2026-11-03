<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Action\MarketTypeBacktests;

use App\Feature\Backtest\Repository\MarketTypeBacktestRunRepository;
use Illuminate\Support\Facades\DB;

class RecordMarketTypeBacktestPairResultAction
{
    public function __construct(
        private readonly MarketTypeBacktestRunRepository $runs,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public function succeed(int $pairRunId, array $result): void
    {
        $this->record($pairRunId, 'succeeded', $result, null);
    }

    public function fail(int $pairRunId): void
    {
        $this->record($pairRunId, 'failed', null, 'Backtest failed. Check application logs for details.');
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function record(int $pairRunId, string $status, ?array $result, ?string $failureMessage): void
    {
        DB::transaction(function () use ($pairRunId, $status, $result, $failureMessage): void {
            $pairRun = $this->runs->lockPairRunForUpdate($pairRunId);

            if (in_array($pairRun->status, ['succeeded', 'failed'], true)) {
                return;
            }

            $this->runs->updatePairRunResult($pairRunId, $status, $result, $failureMessage);

            $run = $this->runs->lockRunForUpdate($pairRun->run_id);
            $counts = $this->runs->pairRunCompletionCounts($run->id);

            if ($counts['terminal'] === $run->total_pairs) {
                $this->runs->updateRun($run->id, [
                    'status' => $counts['failed'] > 0 ? 'completed_with_failures' : 'completed',
                    'completed_at' => now(),
                ]);
            }
        });
    }
}
