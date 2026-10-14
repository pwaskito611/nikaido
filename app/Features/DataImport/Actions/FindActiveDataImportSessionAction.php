<?php

declare(strict_types=1);

namespace App\Features\DataImport\Actions;

use App\Features\DataImport\Models\DataImportSession;
use App\Features\DataImport\Repositories\DataImportSessionRepository;

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
