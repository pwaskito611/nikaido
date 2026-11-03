<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\Fundamentals\Repository\FundamentalObservationImportRepository;

class ReceiveFundamentalObservationsAction
{
    public function __construct(
        private readonly FundamentalObservationImportRepository $observations,
    ) {}

    /**
     * @param  list<array{code: string, name: string, datetime: string, released_at: ?string, value: mixed}>  $batch
     * @return array{imported: int, inserted: int, updated: int}
     */
    public function execute(FundamentalImportSession $session, array $batch): array
    {
        return $this->observations->import((int) $session->fundamental_id, $batch);
    }
}
