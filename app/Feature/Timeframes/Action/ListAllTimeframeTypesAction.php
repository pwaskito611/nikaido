<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
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
