<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Repository\FundamentalRepository;

class DeleteFundamentalAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(int $id): ?bool
    {
        return $this->fundamentals->delete($id);
    }
}
