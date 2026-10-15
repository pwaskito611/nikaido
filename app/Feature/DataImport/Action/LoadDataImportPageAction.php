<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Action;

use App\Feature\DataImport\Model\DataImportSession;
use App\Feature\DataImport\Repository\DataImportSessionRepository;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
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
