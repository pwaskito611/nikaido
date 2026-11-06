<?php

declare(strict_types=1);

namespace App\Feature\News\Model;

final readonly class StartedNewsImportSession
{
    public function __construct(
        public NewsImportSession $session,
        public string $token,
    ) {}
}
