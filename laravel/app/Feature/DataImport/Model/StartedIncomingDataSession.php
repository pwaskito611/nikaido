<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Model;

final readonly class StartedIncomingDataSession
{
    public function __construct(
        public DataImportSession $session,
        public string $token,
    ) {}
}
