<?php

declare(strict_types=1);

namespace App\Feature\MarketExploration\Action;

use App\Feature\MarketPairs\Action\ListAllMarketPairsAction;
use App\Feature\MarketPairs\Model\MarketPair;
use App\Feature\Timeframes\Action\ListAllTimeframeTypesAction;
use App\Feature\Timeframes\Model\TimeframeType;
use Exact\Collection\Vec;

class LoadMarketExplorationPageAction
{
    public function __construct(
        private readonly LoadMarketExplorationCandlesAction $loadCandles,
        private readonly ListAllMarketPairsAction $listMarketPairs,
        private readonly ListAllTimeframeTypesAction $listTimeframes,
    ) {}

    /**
     * @return array{
     *     marketPairs: Vec<MarketPair>,
     *     timeframes: Vec<TimeframeType>,
     *     selectedPairId: ?int,
     *     selectedTimeframeId: ?int,
     *     selectedPair: ?MarketPair,
     *     selectedTimeframe: ?TimeframeType,
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

        $marketPairs = $this->listMarketPairs->execute();
        $timeframes = $this->listTimeframes->execute();
        $selectedPair = $pairId === null
            ? null
            : $marketPairs->filter(static fn (MarketPair $pair): bool => $pair->id === $pairId)->firstOrNull();
        $selectedTimeframe = $timeframeTypeId === null
            ? null
            : $timeframes->filter(static fn (TimeframeType $timeframe): bool => $timeframe->id === $timeframeTypeId)->firstOrNull();
        $hasSearched = $pairId !== null && $timeframeTypeId !== null && $from !== null && $to !== null;

        return [
            'marketPairs' => $marketPairs,
            'timeframes' => $timeframes,
            'selectedPairId' => $pairId,
            'selectedTimeframeId' => $timeframeTypeId,
            'selectedPair' => $selectedPair,
            'selectedTimeframe' => $selectedTimeframe,
            'from' => $from,
            'to' => $to,
            'candles' => $candles,
            'hasSearched' => $hasSearched,
        ];
    }
}
