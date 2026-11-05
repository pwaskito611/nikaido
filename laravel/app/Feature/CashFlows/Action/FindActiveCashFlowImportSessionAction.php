<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Action;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\CashFlows\Repository\CashFlowImportSessionRepository;

class FindActiveCashFlowImportSessionAction
{
    public function __construct(private readonly CashFlowImportSessionRepository $sessions) {}

    public function execute(?string $token): ?CashFlowImportSession
    {
        if ($token === null || $token === '') {
            return null;
        }

        return $this->sessions->findActiveByToken($token);
    }
}
