<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Data\FundamentalDefinition;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Repository\FundamentalRepository;

class UpdateFundamentalAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(int $id, FundamentalDefinition $definition): Fundamental
    {
        return $this->fundamentals->update($id, $definition);
    }
}
