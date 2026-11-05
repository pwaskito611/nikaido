<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Repository;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\MarketPairs\Model\MarketPair;

class CashFlowImportSessionRepository
{
    public function createActive(int $marketPairId, string $tokenHash): CashFlowImportSession
    {
        return CashFlowImportSession::query()->create([
            'market_pair_id' => $marketPairId,
            'active_market_pair_id' => $marketPairId,
            'token_hash' => $tokenHash,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function findActiveByToken(string $token): ?CashFlowImportSession
    {
        return CashFlowImportSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->first();
    }

    public function hasActiveForMarketPair(int $marketPairId): bool
    {
        return CashFlowImportSession::query()
            ->where('market_pair_id', $marketPairId)
            ->where('status', 'active')
            ->exists();
    }

    /** @return list<CashFlowImportSession> */
    public function activeAll(): array
    {
        return CashFlowImportSession::query()
            ->with('marketPair')
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->get()
            ->all();
    }

    /** @return list<CashFlowImportSession> */
    public function recentAll(int $limit = 10): array
    {
        return CashFlowImportSession::query()
            ->with('marketPair')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function stopActive(int $marketPairId, int $sessionId): bool
    {
        return CashFlowImportSession::query()
            ->whereKey($sessionId)
            ->where('market_pair_id', $marketPairId)
            ->where('status', 'active')
            ->update([
                'status' => 'stopped',
                'active_market_pair_id' => null,
                'stopped_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function lockMarketPair(int $marketPairId): void
    {
        MarketPair::query()->whereKey($marketPairId)->lockForUpdate()->firstOrFail();
    }

    public function referencesMarketPair(int $marketPairId): bool
    {
        return CashFlowImportSession::query()->where('market_pair_id', $marketPairId)->exists();
    }
}
