<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Data\FundamentalDefinition;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Repository\FundamentalRepository;

class CreateFundamentalAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(FundamentalDefinition $definition): Fundamental
    {
        return $this->fundamentals->create($definition);
    }
}
