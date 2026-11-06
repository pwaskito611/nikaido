<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Model\StartedNewsImportSession;
use App\Feature\News\Repository\NewsImportSessionRepository;

class StartNewsImportSessionAction
{
    public function __construct(private readonly NewsImportSessionRepository $sessions) {}

    public function execute(int $pairId): StartedNewsImportSession
    {
        $result = $this->sessions->start($pairId);

        return new StartedNewsImportSession($result['session'], $result['token']);
    }
}
