<?php

declare(strict_types=1);

namespace App\Features\DataImport\Actions;

use App\Features\DataImport\Models\DataImportSession;
use App\Features\DataImport\Repositories\DataImportSessionRepository;
use App\Features\Engine\Models\MarketPair;
use App\Features\Engine\Models\TimeframeType;
use App\Features\Engine\Repositories\MarketPairRepository;
use App\Features\Engine\Repositories\TimeframeTypeRepository;
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
