<?php

declare(strict_types=1);

namespace App\DataImport\Actions;

use App\DataImport\Models\DataImportSession;
use App\DataImport\Repositories\DataImportSessionRepository;
use App\Engine\Models\MarketPair;
use App\Engine\Models\TimeframeType;
use App\Engine\Repositories\MarketPairRepository;
use App\Engine\Repositories\TimeframeTypeRepository;
use Illuminate\Database\Eloquent\Collection;

class LoadDataImportPageAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly DataImportSessionRepository $sessions,
    ) {}

    /**
     * @return array{
     *     marketPairs: Collection<int, MarketPair>,
     *     timeframes: Collection<int, TimeframeType>,
     *     activeSessions: Collection<int, DataImportSession>,
     *     recentSessions: Collection<int, DataImportSession>
     * }
     */
    public function execute(): array
    {
        return [
            'marketPairs' => $this->marketPairs->all(),
            'timeframes' => $this->timeframes->all(),
            'activeSessions' => $this->sessions->allActive(),
            'recentSessions' => $this->sessions->recent(10),
        ];
    }
}
