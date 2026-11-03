<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
use Exact\Collection\Vec;

class ListAllTimeframeTypesAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    /**
     * @return Vec<TimeframeType>
     */
    public function execute(): Vec
    {
        return $this->timeframeTypeRepository->all();
    }
}
