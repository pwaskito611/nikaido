<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Repository;

use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalImportSession;

class FundamentalImportSessionRepository
{
    public function createActive(int $fundamentalId, string $tokenHash): FundamentalImportSession
    {
        return FundamentalImportSession::query()->create([
            'fundamental_id' => $fundamentalId,
            'active_fundamental_id' => $fundamentalId,
            'token_hash' => $tokenHash,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function findActiveByToken(string $token): ?FundamentalImportSession
    {
        return FundamentalImportSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveForFundamental(int $fundamentalId): bool
    {
        return FundamentalImportSession::query()
            ->where('fundamental_id', $fundamentalId)
            ->where('status', 'active')
            ->exists();
    }

    /** @return list<FundamentalImportSession> */
    public function activeAll(): array
    {
        return FundamentalImportSession::query()
            ->with('fundamental')
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->get()
            ->all();
    }

    /** @return list<FundamentalImportSession> */
    public function recentAll(int $limit = 10): array
    {
        return FundamentalImportSession::query()
            ->with('fundamental')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function stopActive(int $fundamentalId, int $sessionId): bool
    {
        return FundamentalImportSession::query()
            ->whereKey($sessionId)
            ->where('fundamental_id', $fundamentalId)
            ->where('status', 'active')
            ->update([
                'status' => 'stopped',
                'active_fundamental_id' => null,
                'stopped_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function lockFundamental(int $fundamentalId): void
    {
        Fundamental::query()->whereKey($fundamentalId)->lockForUpdate()->firstOrFail();
    }
}
