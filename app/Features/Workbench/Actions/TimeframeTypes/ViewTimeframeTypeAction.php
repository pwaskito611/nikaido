<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\TimeframeTypes;

use App\Features\Engine\Models\TimeframeType;
use App\Features\Engine\Repositories\TimeframeTypeRepository;

class ViewTimeframeTypeAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    public function execute(int $id): TimeframeType
    {
        return $this->timeframeTypeRepository->findOrFail($id);
    }
}
