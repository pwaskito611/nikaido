<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Data\FundamentalContentDefinition;
use App\Feature\Fundamentals\Model\FundamentalContent;
use App\Feature\Fundamentals\Repository\FundamentalRepository;

class UpdateFundamentalContentAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(
        int $fundamentalId,
        int $contentId,
        FundamentalContentDefinition $definition,
    ): FundamentalContent {
        return $this->fundamentals->updateContent($fundamentalId, $contentId, $definition);
    }
}
