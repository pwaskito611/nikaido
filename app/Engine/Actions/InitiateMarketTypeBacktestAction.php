<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use App\Engine\Jobs\RunMarketTypeBacktestPairJob;
use App\Engine\Models\MarketPair;
use App\Engine\Models\MarketTypeBacktestRun;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Repositories\MarketTypeBacktestRunRepository;
use App\Engine\Repositories\MarketTypeRepository;
use App\Engine\Repositories\TimeframeTypeRepository;
use DateTimeInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InitiateMarketTypeBacktestAction
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly MarketPairRepository $marketPairs,
        private readonly MarketTypeRepository $marketTypes,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly MarketTypeBacktestRunRepository $runs,
        private readonly RecordMarketTypeBacktestPairResultAction $recordResult,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated): MarketTypeBacktestRun
    {
        $this->ensureAsynchronousQueue();

        $marketType = $this->marketTypes->findOrFail((int) $validated['market_type_id']);
        $timeframe = $this->timeframes->findOrFail((int) $validated['timeframe_type_id']);
        $configuration = $this->configurationForRun($validated, $timeframe->name);

        $run = $this->createRunWithEligiblePairs($marketType->id, $marketType->name, $configuration);
        $this->dispatchPairRuns($run->id);

        return $this->runs->findOrFail($run->id);
    }

    private function ensureAsynchronousQueue(): void
    {
        $connection = config('queue.connections.'.config('queue.default'));
        if (($connection['driver'] ?? null) === 'sync') {
            throw ValidationException::withMessages([
                'test_type' => 'Parallel tests require an asynchronous queue connection. Configure database, Redis, SQS, or another asynchronous driver.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function configurationForRun(array $validated, string $timeframeName): array
    {
        $configuration = $validated;
        $configuration['timeframe_name'] = $timeframeName;
        unset($configuration['market_type_id'], $configuration['test_type']);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function createRunWithEligiblePairs(
        int $marketTypeId,
        string $marketTypeName,
        array $configuration,
    ): MarketTypeBacktestRun {
        return DB::transaction(function () use ($marketTypeId, $marketTypeName, $configuration): MarketTypeBacktestRun {
            $run = $this->runs->createRun([
                'market_type_id' => $marketTypeId,
                'market_type_name' => $marketTypeName,
                'configuration' => $configuration,
                'total_pairs' => 0,
                'status' => 'pending',
                'started_at' => now(),
            ]);

            $totalPairs = $this->createEligiblePairRuns($run->id, $marketTypeId, $configuration);
            $this->runs->updateRun($run->id, [
                'total_pairs' => $totalPairs,
                'status' => $totalPairs === 0 ? 'completed' : 'running',
                'completed_at' => $totalPairs === 0 ? now() : null,
            ]);

            return $this->runs->findOrFail($run->id);
        });
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function createEligiblePairRuns(int $runId, int $marketTypeId, array $configuration): int
    {
        $totalPairs = 0;
        $records = [];
        $eligiblePairs = $this->marketPairs->eligibleForMarketTypeBacktest(
            $marketTypeId,
            (int) $configuration['timeframe_type_id'],
            (string) $configuration['from'],
            (string) $configuration['to'],
        );
        $batchTimestamp = now();

        foreach ($eligiblePairs as $pair) {
            $records[] = $this->pendingPairRunRecord($runId, $pair, $batchTimestamp);
            $totalPairs++;

            if (count($records) === 250) {
                $this->runs->createPendingPairRuns($records);
                $records = [];
                $batchTimestamp = now();
            }
        }
        $this->runs->createPendingPairRuns($records);

        return $totalPairs;
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingPairRunRecord(
        int $runId,
        MarketPair $pair,
        DateTimeInterface $timestamp,
    ): array {
        return [
            'run_id' => $runId,
            'pair_id' => $pair->id,
            'pair_name' => $pair->base.'/'.$pair->quote,
            'status' => 'pending',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function dispatchPairRuns(int $runId): void
    {
        foreach ($this->runs->pendingPairRunIds($runId) as $pairRunId) {
            try {
                $this->dispatcher->dispatch(new RunMarketTypeBacktestPairJob($pairRunId));
            } catch (Throwable $exception) {
                report($exception);
                $this->recordResult->fail($pairRunId);
            }
        }
    }
}
