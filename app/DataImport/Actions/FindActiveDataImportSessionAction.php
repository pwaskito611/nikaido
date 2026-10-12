<?php

declare(strict_types=1);

namespace App\DataImport\Actions;

use App\DataImport\Models\DataImportSession;
use App\DataImport\Repositories\DataImportSessionRepository;

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
