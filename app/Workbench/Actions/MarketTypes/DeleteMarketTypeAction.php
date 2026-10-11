<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketTypes;

use App\Engine\Repositories\MarketTypeRepository;
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
                'market_type' => 'Market type tidak dapat dihapus selama masih memiliki market pair.',
            ]);
        }

        $this->marketTypeRepository->delete($id);
    }
}
