<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Model\NewsImportSession;
use App\Feature\News\Repository\NewsImportSessionRepository;

class FindActiveNewsImportSessionAction
{
    public function __construct(private readonly NewsImportSessionRepository $sessions) {}

    public function execute(?string $token): ?NewsImportSession
    {
        return $this->sessions->findActiveByToken($token);
    }
}
