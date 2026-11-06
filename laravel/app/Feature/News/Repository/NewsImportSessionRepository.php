<?php

declare(strict_types=1);

namespace App\Feature\News\Repository;

use App\Feature\News\Model\NewsImportSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsImportSessionRepository
{
    public function start(int $pairId): array
    {
        $token = Str::random(64);
        $session = DB::transaction(function () use ($pairId, $token): NewsImportSession {
            NewsImportSession::query()
                ->where('market_pair_id', $pairId)
                ->where('status', 'active')
                ->update([
                    'status' => 'stopped',
                    'active_market_pair_id' => null,
                    'stopped_at' => now(),
                ]);

            return NewsImportSession::query()->create([
                'market_pair_id' => $pairId,
                'active_market_pair_id' => $pairId,
                'token_hash' => hash('sha256', $token),
                'status' => 'active',
                'started_at' => now(),
            ]);
        });

        return ['session' => $session, 'token' => $token];
    }

    public function findActiveByToken(?string $token): ?NewsImportSession
    {
        if ($token === null || $token === '') {
            return null;
        }

        return NewsImportSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->first();
    }

    public function stop(int $sessionId): bool
    {
        return NewsImportSession::query()
            ->whereKey($sessionId)
            ->where('status', 'active')
            ->update([
                'status' => 'stopped',
                'active_market_pair_id' => null,
                'stopped_at' => now(),
            ]) === 1;
    }

    /** @return list<NewsImportSession> */
    public function recent(): array
    {
        return NewsImportSession::query()->with('marketPair')->latest('id')->limit(20)->get()->all();
    }

    /** @return list<NewsImportSession> */
    public function active(): array
    {
        return NewsImportSession::query()
            ->with('marketPair')
            ->where('status', 'active')
            ->latest('id')
            ->get()
            ->all();
    }
}
