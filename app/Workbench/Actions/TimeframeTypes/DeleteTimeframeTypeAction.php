<?php

declare(strict_types=1);

namespace App\Workbench\Actions\TimeframeTypes;

use App\Engine\Repositories\TimeframeTypeRepository;
use Illuminate\Validation\ValidationException;

class DeleteTimeframeTypeAction
{
    public function __construct(
        private readonly TimeframeTypeRepository $timeframeTypeRepository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->timeframeTypeRepository->hasMarketPrices($id)) {
            throw ValidationException::withMessages([
                'timeframe' => 'Timeframe tidak dapat dihapus selama masih digunakan oleh data harga.',
            ]);
        }

        $this->timeframeTypeRepository->delete($id);
    }
}
