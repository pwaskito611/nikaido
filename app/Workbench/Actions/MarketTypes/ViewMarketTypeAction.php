<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypes;

use App\Engine\Models\MarketType;
use App\Engine\Repositories\MarketTypeRepository;

class ViewMarketTypeAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(int $id): MarketType
    {
        return $this->marketTypeRepository->findOrFail($id);
    }
}
