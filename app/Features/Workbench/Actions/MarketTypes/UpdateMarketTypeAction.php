<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypes;

use App\Features\Engine\Models\MarketType;
use App\Features\Engine\Repositories\MarketTypeRepository;

class UpdateMarketTypeAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(int $id, string $name): MarketType
    {
        return $this->marketTypeRepository->update($id, ['name' => $name]);
    }
}
