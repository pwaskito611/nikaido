<?php

declare(strict_types=1);

namespace App\Feature\MarketTypes\Action;

use App\Feature\MarketTypes\Repository\MarketTypeRepository;
use Illuminate\Validation\ValidationException;

class DeleteMarketTypeAction
{
    public function __construct(
        private readonly MarketTypeRepository $marketTypeRepository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->marketTypeRepository->hasMarketPairs($id)) {
            throw ValidationException::withMessages([
                'market_type' => 'This market type cannot be deleted while it has market pairs.',
            ]);
        }

        $this->marketTypeRepository->delete($id);
    }
}
