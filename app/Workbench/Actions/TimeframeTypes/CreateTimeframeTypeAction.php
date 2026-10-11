<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\Engine\Models\TimeframeType;
use App\Engine\Repositories\TimeframeTypeRepository;

class CreateTimeframeTypeAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    public function execute(string $name): TimeframeType
    {
        return $this->timeframeTypeRepository->create(['name' => $name]);
    }
}
