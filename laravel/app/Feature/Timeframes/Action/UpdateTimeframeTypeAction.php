<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;

class UpdateTimeframeTypeAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    public function execute(int $id, string $name): TimeframeType
    {
        return $this->timeframeTypeRepository->update($id, ['name' => $name]);
    }
}
