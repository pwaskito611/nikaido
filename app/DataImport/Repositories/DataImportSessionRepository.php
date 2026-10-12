<?php

declare(strict_types=1);

namespace App\DataImport\Repositories;

use App\DataImport\Models\DataImportSession;
use Illuminate\Database\Eloquent\Collection;

class DataImportSessionRepository
{
    public function allActive(): Collection
    {
        return DataImportSession::query()
            ->with(['pair', 'timeframeType'])
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->get();
    }

    public function recent(int $limit): Collection
    {
        return DataImportSession::query()
            ->with(['pair', 'timeframeType'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function findActiveByToken(string $token): ?DataImportSession
    {
        return DataImportSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveSelection(int $pairId, int $timeframeTypeId): bool
    {
        return DataImportSession::query()
            ->where('pair_id', $pairId)
            ->where('timeframe_type_id', $timeframeTypeId)
            ->where('status', 'active')
            ->exists();
    }

    public function createActive(
        int $pairId,
        int $timeframeTypeId,
        string $tokenHash,
    ): DataImportSession {
        return DataImportSession::query()->create([
            'pair_id' => $pairId,
            'timeframe_type_id' => $timeframeTypeId,
            'token_hash' => $tokenHash,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function stopActive(int $sessionId): bool
    {
        return DataImportSession::query()
            ->whereKey($sessionId)
            ->where('status', 'active')
            ->update([
                'status' => 'stopped',
                'stopped_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function referencesPair(int $pairId): bool
    {
        return DataImportSession::query()->where('pair_id', $pairId)->exists();
    }

    public function referencesTimeframe(int $timeframeTypeId): bool
    {
        return DataImportSession::query()->where('timeframe_type_id', $timeframeTypeId)->exists();
    }
}
