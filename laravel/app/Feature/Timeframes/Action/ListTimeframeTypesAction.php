<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListTimeframeTypesAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    public function execute(): LengthAwarePaginator
    {
        return $this->timeframeTypeRepository->paginate();
    }
}
