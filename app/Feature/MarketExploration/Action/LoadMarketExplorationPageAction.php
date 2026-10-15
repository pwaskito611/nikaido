<?php

declare(strict_types=1);

namespace App\Feature\MarketExploration\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use Illuminate\Database\Eloquent\Collection;

class LoadMarketExplorationPageAction
{
    public function __construct(
        private readonly LoadMarketExplorationCandlesAction $loadCandles,
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPairs: Collection,
     *     timeframes: Collection,
     *     selectedPairId: ?int,
     *     selectedTimeframeId: ?int,
     *     from: ?string,
     *     to: ?string,
     *     candles: list<array{time: string, open: float, high: float, low: float, close: float}>,
     *     hasSearched: bool
     * }
     */
    public function execute(
        ?int $pairId,
        ?int $timeframeTypeId,
        ?string $from,
        ?string $to,
    ): array {
        $candles = [];

        if ($pairId !== null && $timeframeTypeId !== null && $from !== null && $to !== null) {
            $candles = $this->loadCandles->execute($pairId, $timeframeTypeId, $from, $to);
        }

        $hasSearched = $pairId !== null && $timeframeTypeId !== null && $from !== null && $to !== null;

        return [
            'marketPairs' => $this->listMarketPairs->execute(),
            'timeframes' => $this->listTimeframes->execute(),
            'selectedPairId' => $pairId,
            'selectedTimeframeId' => $timeframeTypeId,
            'from' => $from,
            'to' => $to,
            'candles' => $candles,
            'hasSearched' => $hasSearched,
        ];
    }
}
