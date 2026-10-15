<?php

declare(strict_types=1);

namespace App\Feature\Timeframes\Action;

use App\Feature\DataImport\Repository\DataImportSessionRepository;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
use Illuminate\Validation\ValidationException;

class DeleteTimeframeTypeAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
        private readonly DataImportSessionRepository $dataImportSessions,
    ) {}

    public function execute(int $id): void
    {
        if ($this->timeframeTypeRepository->hasMarketPrices($id)) {
            throw ValidationException::withMessages([
                'timeframe' => 'This timeframe cannot be deleted while it is used by price data.',
            ]);
        }

        if ($this->dataImportSessions->referencesTimeframe($id)) {
            throw ValidationException::withMessages([
                'timeframe' => 'This timeframe cannot be deleted because it is referenced by import sessions.',
            ]);
        }

        $this->timeframeTypeRepository->delete($id);
    }
}
