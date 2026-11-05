<?php

declare(strict_types=1);

namespace App\Feature\CashFlows\Model;

final readonly class StartedCashFlowImportSession
{
    public function __construct(
        public CashFlowImportSession $session,
        public string $token,
    ) {}
}
