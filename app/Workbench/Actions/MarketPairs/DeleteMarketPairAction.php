<?php

declare(strict_types=1);

namespace App\Workbench\Actions\MarketPairs;

use App\Engine\Repositories\MarketPairRepository;
use Illuminate\Validation\ValidationException;

class DeleteMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->marketPairRepository->hasPrices($id)) {
            throw ValidationException::withMessages([
                'market_pair' => 'Market pair tidak dapat dihapus selama masih memiliki riwayat harga.',
            ]);
        }

        $this->marketPairRepository->delete($id);
    }
}
