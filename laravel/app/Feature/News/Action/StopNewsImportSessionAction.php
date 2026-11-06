<?php

declare(strict_types=1);

namespace App\Feature\News\Action;

use App\Feature\News\Repository\NewsImportSessionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StopNewsImportSessionAction
{
    public function __construct(private readonly NewsImportSessionRepository $sessions) {}

    public function execute(int $sessionId): void
    {
        if (! $this->sessions->stop($sessionId)) {
            throw (new ModelNotFoundException)->setModel('NewsImportSession', [$sessionId]);
        }
    }
}
