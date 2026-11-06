<?php

declare(strict_types=1);

namespace App\Feature\DataImport\Action;

use App\Feature\CashFlows\Model\CashFlowImportSession;
use App\Feature\CashFlows\Repository\CashFlowImportSessionRepository;
use App\Feature\DataImport\Model\DataImportSession;
use App\Feature\DataImport\Repository\DataImportSessionRepository;
use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Model\FundamentalImportSession;
use App\Feature\Fundamentals\Repository\FundamentalImportSessionRepository;
use App\Feature\Fundamentals\Repository\FundamentalRepository;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\MarketPairs\Repository\MarketPairRepository;
use App\Feature\News\Model\NewsImportSession;
use App\Feature\News\Repository\NewsImportSessionRepository;
use App\Feature\Timeframes\Model\TimeframeType;
use App\Feature\Timeframes\Repository\TimeframeTypeRepository;
use Exact\Collection\Vec;

class LoadDataImportPageAction
{
    public function __construct(
        private readonly MarketPairRepository $marketPairs,
        private readonly TimeframeTypeRepository $timeframes,
        private readonly DataImportSessionRepository $sessions,
        private readonly FundamentalRepository $fundamentals,
        private readonly FundamentalImportSessionRepository $fundamentalSessions,
        private readonly CashFlowImportSessionRepository $cashFlowSessions,
        private readonly NewsImportSessionRepository $newsSessions,
    ) {}

    /**
     * @return array{
     *     marketPairs: Vec<MarketPair>,
     *     timeframes: Vec<TimeframeType>,
     *     activeSessions: Vec<DataImportSession>,
     *     recentSessions: Vec<DataImportSession>,
     *     fundamentals: Vec<Fundamental>,
     *     activeFundamentalSessions: list<FundamentalImportSession>,
     *     recentFundamentalSessions: list<FundamentalImportSession>,
     *     activeCashFlowSessions: list<CashFlowImportSession>,
     *     recentCashFlowSessions: list<CashFlowImportSession>,
     *     activeNewsSessions: list<NewsImportSession>,
     *     recentNewsSessions: list<NewsImportSession>
     * }
     */
    public function execute(): array
    {
        return [
            'marketPairs' => $this->marketPairs->all(),
            'timeframes' => $this->timeframes->all(),
            'activeSessions' => $this->sessions->allActive(),
            'recentSessions' => $this->sessions->recent(10),
            'fundamentals' => $this->fundamentals->allForImport(),
            'activeFundamentalSessions' => $this->fundamentalSessions->activeAll(),
            'recentFundamentalSessions' => $this->fundamentalSessions->recentAll(),
            'activeCashFlowSessions' => $this->cashFlowSessions->activeAll(),
            'recentCashFlowSessions' => $this->cashFlowSessions->recentAll(),
            'activeNewsSessions' => $this->newsSessions->active(),
            'recentNewsSessions' => $this->newsSessions->recent(),
        ];
    }
}
