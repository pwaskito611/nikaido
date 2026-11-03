<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Repository\FundamentalRepository;

class DeleteFundamentalContentAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(int $fundamentalId, int $contentId): ?bool
    {
        return $this->fundamentals->deleteContent($fundamentalId, $contentId);
    }
}
