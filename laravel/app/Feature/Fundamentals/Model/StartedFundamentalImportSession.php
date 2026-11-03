<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Model;

final readonly class StartedFundamentalImportSession
{
    public function __construct(
        public FundamentalImportSession $session,
        public string $token,
    ) {}
}
