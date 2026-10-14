<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketTypes;

use App\Features\Engine\Models\MarketType;
use App\Features\Engine\Repositories\MarketTypeRepository;

class CreateMarketTypeAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(string $name): MarketType
    {
        return $this->marketTypeRepository->create(['name' => $name]);
    }
}
