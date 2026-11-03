<?php

declare(strict_types=1);

namespace App\Feature\Fundamentals\Action;

use App\Feature\Fundamentals\Model\Fundamental;
use App\Feature\Fundamentals\Repository\FundamentalRepository;
use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use Exact\Collection\Vec;

class LoadFundamentalFormPageAction
{
    public function __construct(
        private readonly FundamentalRepository $fundamentals,
        private readonly ListAllMarketPairsAction $listMarketPairs,
    ) {}

    /**
     * @return array{fundamental: Fundamental, marketPairs: Vec<MarketPair>}
     */
    public function execute(?int $fundamentalId = null): array
    {
        return [
            'fundamental' => $fundamentalId === null
                ? new Fundamental
                : $this->fundamentals->findOrFail($fundamentalId),
            'marketPairs' => $this->listMarketPairs->execute(),
        ];
    }
}
