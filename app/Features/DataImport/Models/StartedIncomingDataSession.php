<?php

declare(strict_types=1);

namespace App\Features\DataImport\Models;

final readonly class StartedIncomingDataSession
{
    public function __construct(
        public DataImportSession $session,
        public string $token,
    ) {}
}
