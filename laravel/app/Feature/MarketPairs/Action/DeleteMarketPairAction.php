<?php

declare(strict_types=1);

namespace App\Feature\MarketPairs\Action;

use App\Feature\CashFlows\Repository\CashFlowImportSessionRepository;
use App\Feature\DataImport\Repository\DataImportSessionRepository;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use Illuminate\Validation\ValidationException;

class DeleteMarketPairAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
        private readonly DataImportSessionRepository $dataImportSessions,
        private readonly CashFlowImportSessionRepository $cashFlowImportSessions,
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

        if ($this->marketPairRepository->hasCashFlows($id)) {
            throw ValidationException::withMessages([
                'market_pair' => 'This market pair cannot be deleted while it has cash flow history.',
            ]);
        }

        if ($this->cashFlowImportSessions->referencesMarketPair($id)) {
            throw ValidationException::withMessages([
                'market_pair' => 'This market pair cannot be deleted because it is referenced by cash flow import sessions.',
            ]);
        }

        $this->marketPairRepository->delete($id);
    }
}
