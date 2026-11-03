<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Repository\FundamentalRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ViewFundamentalAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    /** @return array{fundamental: Fundamental, contents: LengthAwarePaginator} */
    public function execute(int $fundamentalId): array
    {
        return [
            'fundamental' => $this->fundamentals->findOrFail($fundamentalId),
            'contents' => $this->fundamentals->paginateContents($fundamentalId),
        ];
    }
}
