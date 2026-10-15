<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Action;

use App\Feature\DataImport\Repository\DataImportSessionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StopIncomingDataSessionAction
{
    public function __construct(
        private readonly DataImportSessionRepository $sessions,
    ) {}

    public function execute(int $sessionId): void
    {
        if (! $this->sessions->stopActive($sessionId)) {
            throw (new ModelNotFoundException)->setModel('DataImportSession', [$sessionId]);
        }
    }
}
