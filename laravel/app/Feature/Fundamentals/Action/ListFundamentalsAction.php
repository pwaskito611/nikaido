<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Repository\FundamentalRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListFundamentalsAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(): LengthAwarePaginator
    {
        return $this->fundamentals->paginate();
    }
}
