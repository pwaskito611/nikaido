<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\Engine\Models\TimeframeType;
use App\Engine\Repositories\TimeframeTypeRepository;
use Illuminate\Database\Eloquent\Collection;

class ListAllTimeframeTypesAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    /**
     * @return Collection<int, TimeframeType>
     */
    public function execute(): Collection
    {
        return $this->timeframeTypeRepository->all();
    }
}
