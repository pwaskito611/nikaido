<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\DataImport\Repositories\DataImportSessionRepository;
use App\Engine\Repositories\TimeframeTypeRepository;
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
                'timeframe' => 'Timeframe tidak dapat dihapus selama masih digunakan oleh data harga.',
            ]);
        }

        if ($this->dataImportSessions->referencesTimeframe($id)) {
            throw ValidationException::withMessages([
                'timeframe' => 'Timeframe tidak dapat dihapus karena memiliki riwayat sesi import.',
            ]);
        }

        $this->timeframeTypeRepository->delete($id);
    }
}
