<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\Engine\Models\TimeframeType;
use App\Engine\Repositories\TimeframeTypeRepository;

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
