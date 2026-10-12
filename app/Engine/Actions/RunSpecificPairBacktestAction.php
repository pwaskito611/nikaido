<?php

declare(strict_types=1);

namespace App\Engine\Actions;

use App\Engine\Config\SpecificPairTestConfig;
use App\Engine\Execution\FixedSlippage;
use App\Engine\Execution\PartialFill;
use App\Engine\Models\BacktestOrderHistoryEntry;
use App\Engine\Models\MarketPrice;
use App\Engine\Repositories\MarketPairRepository;
use App\Strategy\Enums\StrategySignal;
use App\Strategy\Models\StrategyDecision;
use App\Strategy\Strategy;
use Exact\Collection\Vec;
use Illuminate\Database\Eloquent\Collection;
use UnexpectedValueException;

class RunSpecificPairBacktestAction
{
    private readonly FixedSlippage $fixedSlippage;

    private readonly PartialFill $partialFill;

    public function __construct(
        private readonly MarketPairRepository $marketPairRepository,
        private readonly Strategy $strategy,
    ) {
        $this->fixedSlippage = new FixedSlippage;
        $this->partialFill = new PartialFill;
    }

    /**
     * @return array{
     *     results: Vec<array{candle: MarketPrice, result: bool, signal: string, stop_loss: ?float, take_profit: ?float}>,
     *     order_history: Vec<BacktestOrderHistoryEntry>,
     *     initial_capital: float,
     *     ending_capital: float,
     *     execution_config: array<string, bool|float|string|null>
     * }
     */
    public function execute(SpecificPairTestConfig $config): array
    {
        $pair = $this->marketPairRepository->findWithPricesBetween(
            $config->base,
            $config->quote,
            $config->from,
            $config->to,
            $config->timeframeTypeId,
            $config->marketPairId,
        );

        $decisions = $this->strategy->execute($pair->prices);

        return $this->simulate($pair->prices, $decisions, $config);
    }

    /**
     * @param  Collection<int, MarketPrice>  $candles
     * @param  Vec<StrategyDecision>  $decisions
     * @return array{
     *     results: Vec<array{candle: MarketPrice, result: bool, signal: string, stop_loss: ?float, take_profit: ?float}>,
     *     order_history: Vec<BacktestOrderHistoryEntry>,
     *     initial_capital: float,
     *     ending_capital: float,
     *     execution_config: array<string, bool|float|string|null>
     * }
     */
    private function simulate(
        Collection $candles,
        Vec $decisions,
        SpecificPairTestConfig $config,
    ): array {
        $results = Vec::empty();
        $orderHistory = Vec::empty();
        $openOrderIndex = null;
        $currentCapital = $config->initialCapital;

        foreach ($candles as $index => $candle) {
            if ($candle->price_date < $config->from) {
                continue;
            }

            $decision = $this->isActive($candle, $config)
                ? $decisions->at($index)
                : null;
            $hadOpenOrder = $openOrderIndex !== null;

            if ($openOrderIndex !== null) {
                $order = $orderHistory->at($openOrderIndex);
                if ($order->remainingEntryQuantity > 0 && $config->partialFill->enabled
                    && $config->partialFill->unfilledOrderPolicy === 'keep') {
                    $entry = $this->entryPrice($candle, StrategySignal::from($order->signal));
                    $entryPrice = $this->applySlippage($entry['price'], $order->signal === StrategySignal::Long->value, $config);
                    $fillQuantity = $this->partialFill->quantity(
                        $order->requestedPositionSize,
                        $order->remainingEntryQuantity,
                        $config->partialFill->ratio,
                    );
                    $order->recordEntryFill(
                        $fillQuantity,
                        $entryPrice,
                        $candle->price_date->format('Y-m-d H:i:s'),
                        $entry['source'],
                    );
                }

                $this->updateClosestToStopLoss($order, $candle);
                $this->updateClosestToTakeProfit($order, $candle);
                $this->updateExcursionExtremes($order, $candle);

                $closeReason = $order->pendingExitReason ?? (
                    $order->remainingPositionSize > 0
                        ? $this->getPriceExitReason($order, $candle)
                        : null
                );

                if ($closeReason === null && $decision?->signal === StrategySignal::Close) {
                    $closeReason = 'close';
                }

                if ($closeReason !== null) {
                    $order->cancelledEntryQuantity += $order->remainingEntryQuantity;
                    $order->remainingEntryQuantity = 0.0;
                    $order->pendingExitReason ??= $closeReason;
                    $order->pendingExitRequestedQuantity ??= $order->remainingPositionSize;
                    [$referencePrice, $priceSource] = $this->closePrice($order, $candle, $closeReason);
                    if ($referencePrice === null) {
                        throw new UnexpectedValueException('Cannot calculate trade profit or loss without a closing price.');
                    }
                    $exitPrice = $this->applySlippage(
                        $referencePrice,
                        $order->signal !== StrategySignal::Long->value,
                        $config,
                    );
                    $fillRatio = $config->partialFill->enabled ? $config->partialFill->ratio : 1.0;
                    $fillQuantity = $this->partialFill->quantity(
                        $order->pendingExitRequestedQuantity,
                        $order->remainingPositionSize,
                        $fillRatio,
                    );
                    $fillProfitLoss = $order->recordExitFill(
                        $fillQuantity,
                        $exitPrice,
                        $candle->price_date->format('Y-m-d H:i:s'),
                        $priceSource,
                        $closeReason,
                    );
                    $currentCapital += $fillProfitLoss;
                    $order->exitFills[array_key_last($order->exitFills)]['capital_after'] = $currentCapital;

                    if ($order->remainingPositionSize <= 0.000000000001) {
                        $order->remainingPositionSize = 0.0;
                        $order->closeDate = $candle->price_date->format('Y-m-d H:i:s');
                        $order->closeReason = $order->pendingExitReason;
                        $order->profitLoss = $order->realizedProfitLoss;
                        $order->capitalAfterClose = $currentCapital;
                        $openOrderIndex = null;
                    } elseif ($config->partialFill->unfilledOrderPolicy === 'cancel') {
                        $order->cancelledExitQuantity += max(0.0, $order->pendingExitRequestedQuantity - $fillQuantity);
                        $order->pendingExitReason = null;
                        $order->pendingExitRequestedQuantity = null;
                    }
                }
            }

            if ($decision !== null) {
                $results = $results->append([
                    'candle' => $candle,
                    'result' => $decision->signal !== StrategySignal::None,
                    'signal' => $decision->signal->value,
                    'stop_loss' => $decision->stopLoss,
                    'take_profit' => $decision->takeProfit,
                ]);
            }

            if (! $hadOpenOrder && $openOrderIndex === null && $decision !== null
                && in_array($decision->signal, [StrategySignal::Long, StrategySignal::Short], true)) {
                $entry = $this->entryPrice($candle, $decision->signal);
                $entryPrice = $this->applySlippage(
                    $entry['price'],
                    $decision->signal === StrategySignal::Long,
                    $config,
                );

                if ($decision->stopLoss === null || $decision->takeProfit === null) {
                    throw new UnexpectedValueException('An entry signal must include stop-loss and take-profit values.');
                }

                $riskDistance = abs($entryPrice - $decision->stopLoss);
                if ($riskDistance <= 0) {
                    throw new UnexpectedValueException('Cannot size an order when entry and stop-loss prices are equal.');
                }
                $riskAmount = $currentCapital * ($config->riskPerTradePercent / 100);
                if ($riskAmount <= 0) {
                    throw new UnexpectedValueException('Cannot open an order because available capital is no longer positive.');
                }

                $requestedPositionSize = $riskAmount / $riskDistance;
                $order = new BacktestOrderHistoryEntry(
                    id: $orderHistory->count() + 1,
                    signal: $decision->signal->value,
                    openDate: $candle->price_date->format('Y-m-d H:i:s'),
                    entryPrice: $entryPrice,
                    entryPriceSource: $entry['source'],
                    stopLoss: $decision->stopLoss,
                    takeProfit: $decision->takeProfit,
                    requestedRiskAmount: $riskAmount,
                    positionSize: 0.0,
                    requestedPositionSize: $requestedPositionSize,
                    capitalAtOpen: $currentCapital,
                );
                $fillRatio = $config->partialFill->enabled ? $config->partialFill->ratio : 1.0;
                $fillQuantity = $this->partialFill->quantity(
                    $requestedPositionSize,
                    $requestedPositionSize,
                    $fillRatio,
                );
                $order->recordEntryFill(
                    $fillQuantity,
                    $entryPrice,
                    $candle->price_date->format('Y-m-d H:i:s'),
                    $entry['source'],
                );
                if ($config->partialFill->enabled && $config->partialFill->unfilledOrderPolicy === 'cancel') {
                    $order->cancelledEntryQuantity += $order->remainingEntryQuantity;
                    $order->remainingEntryQuantity = 0.0;
                }
                $orderHistory = $orderHistory->append($order);
                $openOrderIndex = $orderHistory->count() - 1;
            }
        }

        return [
            'results' => $results,
            'order_history' => $orderHistory,
            'initial_capital' => $config->initialCapital,
            'ending_capital' => $currentCapital,
            'execution_config' => $config->executionConfiguration(),
        ];
    }

    private function updateClosestToStopLoss(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $closeQuote = $isLong ? $candle->bid_close : $candle->ask_close;
        $stopQuote = $isLong ? $candle->bid_low : $candle->ask_high;
        $candidates = [
            is_numeric($closeQuote) ? ($isLong ? 'bid_close' : 'ask_close') : 'close' => is_numeric($closeQuote)
                ? $closeQuote
                : $candle->close,
            is_numeric($stopQuote) ? ($isLong ? 'bid_low' : 'ask_high') : ($isLong ? 'low' : 'high') => is_numeric($stopQuote)
                ? $stopQuote
                : ($isLong ? $candle->low : $candle->high),
        ];

        foreach ($candidates as $source => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $price = (float) $value;
            $distance = round(abs($price - $order->stopLoss), 6);
            $previous = $order->closestToStopLoss;

            if ($previous === null || $distance < $previous['distance']) {
                $order->closestToStopLoss = [
                    'price' => $price,
                    'date' => $candle->price_date->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'distance' => $distance,
                ];
            }
        }
    }

    private function updateClosestToTakeProfit(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $closeQuote = $isLong ? $candle->bid_close : $candle->ask_close;
        $takeProfitQuote = $isLong ? $candle->bid_high : $candle->ask_low;
        $closeSource = is_numeric($closeQuote) ? ($isLong ? 'bid_close' : 'ask_close') : 'close';
        $takeProfitSource = is_numeric($takeProfitQuote)
            ? ($isLong ? 'bid_high' : 'ask_low')
            : ($isLong ? 'high' : 'low');
        $candidates = [
            $closeSource => is_numeric($closeQuote) ? $closeQuote : $candle->close,
            $takeProfitSource => is_numeric($takeProfitQuote)
                ? $takeProfitQuote
                : ($isLong ? $candle->high : $candle->low),
        ];

        foreach ($candidates as $source => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $price = (float) $value;
            $distance = round(abs($price - $order->takeProfit), 6);
            $previous = $order->closestToTakeProfit;

            if ($previous === null || $distance < $previous['distance']) {
                $order->closestToTakeProfit = [
                    'price' => $price,
                    'date' => $candle->price_date->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'distance' => $distance,
                ];
            }
        }
    }

    private function updateExcursionExtremes(BacktestOrderHistoryEntry $order, MarketPrice $candle): void
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $adverseQuote = $isLong ? $candle->bid_low : $candle->ask_high;
        $favorableQuote = $isLong ? $candle->bid_high : $candle->ask_low;
        $adverseFallback = $isLong ? $candle->low : $candle->high;
        $favorableFallback = $isLong ? $candle->high : $candle->low;
        $adverse = is_numeric($adverseQuote) ? $adverseQuote : $adverseFallback;
        $favorable = is_numeric($favorableQuote) ? $favorableQuote : $favorableFallback;
        if (! is_numeric($adverse) || ! is_numeric($favorable)) {
            return;
        }

        $adverse = (float) $adverse;
        $favorable = (float) $favorable;
        $timestamp = $candle->price_date->format('Y-m-d H:i:s');
        $adverseSource = is_numeric($adverseQuote)
            ? ($isLong ? 'bid_low' : 'ask_high')
            : ($isLong ? 'low' : 'high');
        $favorableSource = is_numeric($favorableQuote)
            ? ($isLong ? 'bid_high' : 'ask_low')
            : ($isLong ? 'high' : 'low');
        $previousAdverse = $order->adverseExtreme;
        $isMoreAdverse = $previousAdverse === null || ($isLong
            ? $adverse < $previousAdverse['price']
            : $adverse > $previousAdverse['price']);
        if ($isMoreAdverse) {
            $order->adverseExtreme = [
                'price' => $adverse,
                'date' => $timestamp,
                'source' => $adverseSource,
            ];
        }

        $previousFavorable = $order->favorableExtreme;
        $isMoreFavorable = $previousFavorable === null || ($isLong
            ? $favorable > $previousFavorable['price']
            : $favorable < $previousFavorable['price']);
        if ($isMoreFavorable) {
            $order->favorableExtreme = [
                'price' => $favorable,
                'date' => $timestamp,
                'source' => $favorableSource,
            ];
        }
    }

    private function getPriceExitReason(BacktestOrderHistoryEntry $order, MarketPrice $candle): ?string
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $stopPrice = $isLong ? $candle->bid_low : $candle->ask_high;
        $stopFallback = $isLong ? $candle->low : $candle->high;
        $takeProfitPrice = $isLong ? $candle->bid_high : $candle->ask_low;
        $takeProfitFallback = $isLong ? $candle->high : $candle->low;
        $stopPrice = is_numeric($stopPrice) ? $stopPrice : $stopFallback;
        $takeProfitPrice = is_numeric($takeProfitPrice) ? $takeProfitPrice : $takeProfitFallback;

        if (is_numeric($stopPrice) && ($isLong
            ? (float) $stopPrice <= $order->stopLoss
            : (float) $stopPrice >= $order->stopLoss)) {
            // OHLC cannot show which level was touched first, so prefer the adverse exit.
            return 'stop_loss';
        }

        if (is_numeric($takeProfitPrice) && ($isLong
            ? (float) $takeProfitPrice >= $order->takeProfit
            : (float) $takeProfitPrice <= $order->takeProfit)) {
            return 'take_profit';
        }

        return null;
    }

    /**
     * @return array{?float, string}
     */
    private function closePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle, string $reason): array
    {
        if ($reason === 'stop_loss') {
            return $this->marketClosePrice($order, $candle);
        }

        if ($reason === 'take_profit') {
            return [$order->takeProfit, 'take_profit'];
        }

        return $this->marketClosePrice($order, $candle);
    }

    /**
     * @return array{?float, string}
     */
    private function marketClosePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle): array
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $quote = $isLong ? $candle->bid_close : $candle->ask_close;
        $source = is_numeric($quote) ? ($isLong ? 'bid_close' : 'ask_close') : 'close';
        $close = is_numeric($quote) ? $quote : $candle->close;

        return [is_numeric($close) ? (float) $close : null, $source];
    }

    private function applySlippage(float $referencePrice, bool $isBuy, SpecificPairTestConfig $config): float
    {
        if (! $config->slippage->enabled) {
            return $referencePrice;
        }

        $pipSize = $config->slippage->pipSize ?? (strtoupper($config->quote) === 'JPY' ? 0.01 : 0.0001);

        return $this->fixedSlippage->apply($referencePrice, $config->slippage->pips, $pipSize, $isBuy);
    }

    /**
     * @return array{price: float, source: string}
     */
    private function entryPrice(MarketPrice $candle, StrategySignal $signal): array
    {
        $quoteName = $signal === StrategySignal::Long ? 'ask_open' : 'bid_open';
        $quote = $candle->{$quoteName};

        if (is_numeric($quote)) {
            return ['price' => (float) $quote, 'source' => $quoteName];
        }

        if (is_numeric($candle->open)) {
            return ['price' => (float) $candle->open, 'source' => 'open'];
        }

        throw new UnexpectedValueException("Cannot open an order because the candle has no usable {$quoteName} price.");
    }

    private function isActive(MarketPrice $candle, SpecificPairTestConfig $config): bool
    {
        $timestamp = $candle->price_date;

        if (in_array((int) $timestamp->format('N'), $config->excludedDays, true)) {
            return false;
        }

        if ($config->activeFrom === false) {
            return true;
        }

        $time = $timestamp->format('H:i');

        if ($config->activeFrom <= $config->activeUntil) {
            return $time >= $config->activeFrom && $time <= $config->activeUntil;
        }

        return $time >= $config->activeFrom || $time <= $config->activeUntil;
    }
}
