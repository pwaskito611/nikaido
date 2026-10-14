<?php

declare(strict_types=1);

namespace App\Features\Workbench\Actions\MarketPairs;

use App\Features\DataImport\Repositories\DataImportSessionRepository;
use App\Features\Engine\Repositories\MarketPairRepository;
use Illuminate\Validation\ValidationException;

class DeleteMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
        private readonly DataImportSessionRepository $dataImportSessions,
    ) {}

    public function execute(int $id): void
    {
        if ($this->marketPairRepository->hasPrices($id)) {
            throw ValidationException::withMessages([
                'market_pair' => 'This market pair cannot be deleted while it has price history.',
            ]);
        }

        if ($this->dataImportSessions->referencesPair($id)) {
            throw ValidationException::withMessages([
                'market_pair' => 'This market pair cannot be deleted because it is referenced by import sessions.',
            ]);
        }

        $this->marketPairRepository->delete($id);
    }
}
