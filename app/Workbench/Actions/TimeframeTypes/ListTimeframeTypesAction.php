<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\Engine\Repositories\TimeframeTypeRepository;
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
