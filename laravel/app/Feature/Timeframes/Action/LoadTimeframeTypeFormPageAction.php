<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\Timeframes\Model\TimeframeType;

class LoadTimeframeTypeFormPageAction
{
    public function __construct(
        private readonly ViewTimeframeTypeAction $viewTimeframeType,
    ) {}

    public function execute(?int $timeframeTypeId = null): TimeframeType
    {
        return $timeframeTypeId === null
            ? new TimeframeType
            : $this->viewTimeframeType->execute($timeframeTypeId);
    }
}
