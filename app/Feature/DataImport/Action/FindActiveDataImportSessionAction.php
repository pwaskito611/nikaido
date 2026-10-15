<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Action;

use App\Feature\DataImport\Model\DataImportSession;
use App\Feature\DataImport\Repository\DataImportSessionRepository;

class FindActiveDataImportSessionAction
{
    public function __construct(
        private readonly DataImportSessionRepository $sessions,
    ) {}

    public function execute(?string $token): ?DataImportSession
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return $this->sessions->findActiveByToken($token);
    }
}
