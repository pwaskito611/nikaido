<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypes;

use App\Engine\Models\MarketType;
use App\Engine\Repositories\MarketTypeRepository;

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
