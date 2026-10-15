<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Repository;

use App\Feature\Backtest\Model\MarketTypeBacktestPairRun;
use App\Feature\Backtest\Model\MarketTypeBacktestRun;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class MarketTypeBacktestRunRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRun(array $attributes): MarketTypeBacktestRun
    {
        return MarketTypeBacktestRun::query()->create($attributes);
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function createPendingPairRuns(array $records): void
    {
        if ($records !== []) {
            MarketTypeBacktestPairRun::query()->insert($records);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRun(int $runId, array $attributes): MarketTypeBacktestRun
    {
        $run = MarketTypeBacktestRun::query()->findOrFail($runId);
        $run->update($attributes);

        return $run;
    }

    /**
     * @return LazyCollection<int, int>
     */
    public function pendingPairRunIds(int $runId): LazyCollection
    {
        return MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->select('id')
            ->orderBy('id')
            ->lazyById(250)
            ->map(static fn (MarketTypeBacktestPairRun $pairRun): int => $pairRun->id);
    }

    public function findPairRunWithRunOrFail(int $pairRunId): MarketTypeBacktestPairRun
    {
        return MarketTypeBacktestPairRun::query()
            ->with('run')
            ->findOrFail($pairRunId);
    }

    public function markPairRunRunning(int $pairRunId, DateTimeInterface $startedAt): void
    {
        MarketTypeBacktestPairRun::query()
            ->whereKey($pairRunId)
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status' => 'running',
                'started_at' => $startedAt,
                'updated_at' => now(),
            ]);
    }

    public function lockPairRunForUpdate(int $pairRunId): MarketTypeBacktestPairRun
    {
        return MarketTypeBacktestPairRun::query()
            ->lockForUpdate()
            ->findOrFail($pairRunId);
    }

    public function lockRunForUpdate(int $runId): MarketTypeBacktestRun
    {
        return MarketTypeBacktestRun::query()
            ->lockForUpdate()
            ->findOrFail($runId);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function updatePairRunResult(
        int $pairRunId,
        string $status,
        ?array $result,
        ?string $failureMessage,
    ): void {
        MarketTypeBacktestPairRun::query()
            ->whereKey($pairRunId)
            ->update([
                'status' => $status,
                'result' => $result,
                'failure_message' => $failureMessage,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array{terminal: int, failed: int}
     */
    public function pairRunCompletionCounts(int $runId): array
    {
        $counts = MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->selectRaw("SUM(CASE WHEN status IN ('succeeded', 'failed') THEN 1 ELSE 0 END) as terminal")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        return [
            'terminal' => (int) ($counts->terminal ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
        ];
    }

    public function findOrFail(int $runId): MarketTypeBacktestRun
    {
        return MarketTypeBacktestRun::query()->findOrFail($runId);
    }

    /**
     * @return array{pending: int, running: int, succeeded: int, failed: int}
     */
    public function pairRunCounts(int $runId): array
    {
        $counts = MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'running' => (int) ($counts['running'] ?? 0),
            'succeeded' => (int) ($counts['succeeded'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }

    public function paginatePairRuns(int $runId, int $perPage): LengthAwarePaginator
    {
        return MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->forceIndex('market_type_backtest_pair_run_run_id_id_index')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPairRunOrFail(int $runId, int $pairRunId): MarketTypeBacktestPairRun
    {
        return MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->findOrFail($pairRunId);
    }

    /**
     * @return Collection<int, MarketTypeBacktestPairRun>
     */
    public function successfulPairRuns(int $runId): Collection
    {
        return MarketTypeBacktestPairRun::query()
            ->where('run_id', $runId)
            ->where('status', 'succeeded')
            ->forceIndex('market_type_backtest_pair_run_run_id_id_index')
            ->orderBy('id')
            ->get();
    }
}
