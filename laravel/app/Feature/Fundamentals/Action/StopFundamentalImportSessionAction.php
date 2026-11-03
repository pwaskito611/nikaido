<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Repository\FundamentalImportSessionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StopFundamentalImportSessionAction
{
    public function __construct(
        private readonly FundamentalImportSessionRepository $sessions,
    ) {}

    public function execute(int $fundamentalId, int $sessionId): void
    {
        if (! $this->sessions->stopActive($fundamentalId, $sessionId)) {
            throw (new ModelNotFoundException)->setModel('FundamentalImportSession', [$sessionId]);
        }
    }
}
