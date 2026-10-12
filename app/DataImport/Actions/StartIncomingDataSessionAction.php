<?php

declare(strict_types=1);

namespace App\DataImport\Actions;

use App\DataImport\Models\DataImportSession;
use App\DataImport\Models\StartedIncomingDataSession;
use App\DataImport\Repositories\DataImportSessionRepository;
use App\Engine\Repositories\MarketPairRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartIncomingDataSessionAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly DataImportSessionRepository $sessions,
    ) {}

    public function execute(int $pairId, int $timeframeTypeId): StartedIncomingDataSession
    {
        $token = Str::random(64);
        $session = DB::transaction(function () use ($pairId, $timeframeTypeId, $token): DataImportSession {
            $this->marketPairs->lockForUpdate($pairId);
            if ($this->sessions->hasActiveSelection($pairId, $timeframeTypeId)) {
                throw ValidationException::withMessages([
                    'pair_id' => 'An incoming data session is already active for this pair and timeframe.',
                ]);
            }

            return $this->sessions->createActive($pairId, $timeframeTypeId, hash('sha256', $token));
        });

        return new StartedIncomingDataSession($session, $token);
    }
}
