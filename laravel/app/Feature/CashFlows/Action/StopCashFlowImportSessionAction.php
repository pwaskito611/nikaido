<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Repository\CashFlowImportSessionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StopCashFlowImportSessionAction
{
    public function __construct(private readonly CashFlowImportSessionRepository $sessions) {}

    public function execute(int $marketPairId, int $sessionId): void
    {
        if (! $this->sessions->stopActive($marketPairId, $sessionId)) {
            throw (new ModelNotFoundException)->setModel('CashFlowImportSession', [$sessionId]);
        }
    }
}
