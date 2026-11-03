<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Data\FundamentalContentDefinition;
use App\Feature\Fundamentals\Model\FundamentalContent;
use App\Feature\Fundamentals\Repository\FundamentalRepository;

class CreateFundamentalContentAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(int $fundamentalId, FundamentalContentDefinition $definition): FundamentalContent
    {
        return $this->fundamentals->createContent($fundamentalId, $definition);
    }
}
