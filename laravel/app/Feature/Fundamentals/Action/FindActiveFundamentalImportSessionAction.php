<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\Fundamentals\Repository\FundamentalImportSessionRepository;

class FindActiveFundamentalImportSessionAction
{
    public function __construct(
        private readonly FundamentalImportSessionRepository $sessions,
    ) {}

    public function execute(?string $token): ?FundamentalImportSession
    {
        if ($token === null || $token === '') {
            return null;
        }

        return $this->sessions->findActiveByToken($token);
    }
}
