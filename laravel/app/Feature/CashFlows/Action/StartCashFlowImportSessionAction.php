<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\CashFlows\Model\StartedCashFlowImportSession;
use App\Feature\CashFlows\Repository\CashFlowImportSessionRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartCashFlowImportSessionAction
{
    public function __construct(private readonly CashFlowImportSessionRepository $sessions) {}

    public function execute(int $marketPairId): StartedCashFlowImportSession
    {
        $token = Str::random(64);

        try {
            $session = DB::transaction(function () use ($marketPairId, $token): CashFlowImportSession {
                $this->sessions->lockMarketPair($marketPairId);
                if ($this->sessions->hasActiveForMarketPair($marketPairId)) {
                    throw ValidationException::withMessages([
                        'market_pair_id' => 'A cash flow receiver is already active for this market pair.',
                    ]);
                }

                return $this->sessions->createActive($marketPairId, hash('sha256', $token));
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'market_pair_id' => 'A cash flow receiver is already active for this market pair.',
            ]);
        }

        return new StartedCashFlowImportSession($session, $token);
    }
}
