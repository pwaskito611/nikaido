<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Repository\FundamentalRepository;
use DateTimeInterface;
use Nikaido\Strategy\Models\FundamentalDataCollection;

class LoadFundamentalDataForBacktestAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
    ) {}

    public function execute(
        int $marketPairId,
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): FundamentalDataCollection {
        return $this->fundamentals->forMarketPairBacktest($marketPairId, $from, $to);
    }
}
