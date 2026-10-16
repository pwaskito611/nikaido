<?php

declare(strict_types=1);

namespace App\Feature\Backtest\Process;

use App\Feature\Backtest\Config\SpecificPairTestConfig;
use App\Feature\Backtest\Config\UnfilledOrderPolicy;
use App\Feature\Backtest\Data\BacktestDecision;
use App\Feature\Backtest\Data\BacktestExecutionConfiguration;
use App\Feature\Backtest\Data\BacktestPriceObservation;
use App\Feature\Backtest\Data\SpecificPairBacktestResult;
use App\Feature\Backtest\Data\StressTestPriceSource;
use App\Feature\Backtest\Data\StressTestTimestamp;
use App\Feature\Backtest\Execution\FixedSlippage;
use App\Feature\Backtest\Execution\PartialFill;
use App\Feature\Backtest\Model\BacktestOrderHistoryEntry;
use App\Feature\MarketPairs\Data\MarketPairMarketData;
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Platform\Strategy\Enums\StrategySignal;
use App\Platform\Strategy\Models\StrategyDecision;
use App\Platform\Strategy\Models\StrategyExecutionContext;
use App\Platform\Strategy\Strategy;
use ArrayIterator;
use Exact\Collection\Vec;
use Exact\Type\Option\Option;
use Exact\Type\Tuple\Pair;
use Gauss\Number\Number;
use Iterator;
use IteratorIterator;
use Traversable;
use UnexpectedValueException;

class SpecificPairBacktestProcess
{
    private readonly FixedSlippage $fixedSlippage;

    private readonly PartialFill $partialFill;

    private readonly TradeCostCalculator $tradeCostCalculator;

    public function __construct(
        private readonly Strategy $strategy,
    ) {
        $this->fixedSlippage = new FixedSlippage;
        $this->partialFill = new PartialFill;
        $this->tradeCostCalculator = new TradeCostCalculator;
    }

    public function execute(
        SpecificPairTestConfig $config,
        MarketPairMarketData $marketData,
    ): SpecificPairBacktestResult {
        $quantityUnit = $marketData->quantityUnit->value;
        $configuredQuantityMultiplier = $marketData->contractSize;
        $quantityMultiplier = $quantityUnit === 'units' ? 1.0 : (float) $configuredQuantityMultiplier;
        $candles = $marketData->candles;
        $context = new StrategyExecutionContext(
            $config->initialCapital,
            $quantityUnit,
            $quantityMultiplier,
        );
        $decisions = $this->strategy->execute($candles, $context);

        return $this->simulate(
            $candles,
            $decisions,
            $config,
            $quantityUnit,
            $configuredQuantityMultiplier,
            $context,
        );
    }

    /**
     * @param  Vec<MarketPrice>  $candles
     * @param  iterable<int, StrategyDecision>  $decisions
     */
    private function simulate(
        Vec $candles,
        iterable $decisions,
        SpecificPairTestConfig $config,
        string $quantityUnit,
        string $configuredQuantityMultiplier,
        StrategyExecutionContext $context,
    ): SpecificPairBacktestResult {
        $results = Vec::empty();
        $orderHistory = Vec::empty();
        $openOrderIndex = null;
        $currentCapital = $config->initialCapital;
        $decisionIterator = $this->decisionIterator($decisions);
        $decisionIterator->rewind();

        foreach ($candles as $index => $candle) {
            if (! $decisionIterator->valid()) {
                throw new UnexpectedValueException('The strategy must return one decision for every candle.');
            }

            $currentDecision = $decisionIterator->current();

            if ($candle->price_date < $config->from) {
                $decisionIterator->next();

                continue;
            }

            $decision = $this->isActive($candle, $config)
                ? $currentDecision
                : null;
            $hadOpenOrder = $openOrderIndex !== null;

            if ($openOrderIndex !== null) {
                $order = $orderHistory->at($openOrderIndex);
                if ($order->remainingEntryQuantity > 0 && $config->partialFill->enabled
                    && $config->partialFill->unfilledOrderPolicy === UnfilledOrderPolicy::Keep) {
                    $entry = $this->entryPrice($candle, StrategySignal::from($order->signal));
                    $entryPrice = $this->applySlippage($entry->first(), $order->signal === StrategySignal::Long->value, $config);
                    $fillQuantity = $this->partialFill->quantity(
                        $order->requestedPositionSize,
                        $order->remainingEntryQuantity,
                        $config->partialFill->ratio,
                    );
                    $order->recordEntryFill(
                        $fillQuantity,
                        $entryPrice,
                        $candle->price_date->format('Y-m-d H:i:s'),
                        $entry->second(),
                    );
                    $entryCosts = $this->transactionCosts($order, $fillQuantity, $entryPrice, $config, 'entry');
                    $order->recordEntryTradeCosts($entryCosts);
                    $currentCapital = (float) Number::of($currentCapital)
                        ->sub($this->sumCosts($entryCosts))
                        ->value();
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
                    $close = $this->closePrice($order, $candle, $closeReason);
                    $referencePrice = $close->first()->getOrNull();
                    $priceSource = $close->second();
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
                    $exitDate = $candle->price_date->format('Y-m-d H:i:s');
                    $exitCosts = $this->transactionCosts($order, $fillQuantity, $exitPrice, $config, 'exit');
                    if ($config->tradeCosts->enabled) {
                        $notionalDays = $order->consumeEntryNotionalDays($fillQuantity, $exitDate);
                        $components = $config->tradeCosts->components();
                        if ($order->signal === StrategySignal::Short->value) {
                            $exitCosts['borrow_fee'] = $this->tradeCostCalculator->holding(
                                $components['borrow_fee'],
                                $notionalDays,
                            );
                        }
                        $exitCosts['financing_fee'] = $this->tradeCostCalculator->holding(
                            $components['financing_fee'],
                            $notionalDays,
                        );
                    }
                    $fillProfitLoss = $order->recordExitFill(
                        $fillQuantity,
                        $exitPrice,
                        $exitDate,
                        $priceSource,
                        $closeReason,
                        $exitCosts,
                    );
                    $currentCapital = (float) Number::of($currentCapital)
                        ->add($fillProfitLoss)
                        ->value();
                    $order->recordExitCapitalAfter($currentCapital);

                    if ($order->remainingPositionSize <= 0.000000000001) {
                        $order->remainingPositionSize = 0.0;
                        $order->closeDate = $candle->price_date->format('Y-m-d H:i:s');
                        $order->closeReason = $order->pendingExitReason;
                        $order->profitLoss = $order->realizedProfitLoss;
                        $order->capitalAfterClose = $currentCapital;
                        $openOrderIndex = null;
                    } elseif ($config->partialFill->unfilledOrderPolicy === UnfilledOrderPolicy::Cancel) {
                        $order->cancelledExitQuantity += max(0.0, $order->pendingExitRequestedQuantity - $fillQuantity);
                        $order->pendingExitReason = null;
                        $order->pendingExitRequestedQuantity = null;
                    }
                }
            }

            if ($decision !== null) {
                $results = $results->append(new BacktestDecision(
                    candle: $candle,
                    entered: $decision->signal !== StrategySignal::None,
                    signal: $decision->signal,
                    quantity: Option::from($decision->quantity),
                    quantityUnit: $quantityUnit,
                    stopLoss: Option::from($decision->stopLoss),
                    takeProfit: Option::from($decision->takeProfit),
                ));
            }

            if (! $hadOpenOrder && $openOrderIndex === null && $decision !== null
                && in_array($decision->signal, [StrategySignal::Long, StrategySignal::Short], true)) {
                $entry = $this->entryPrice($candle, $decision->signal);
                $entryPrice = $this->applySlippage(
                    $entry->first(),
                    $decision->signal === StrategySignal::Long,
                    $config,
                );

                if ($decision->stopLoss === null || $decision->takeProfit === null) {
                    throw new UnexpectedValueException('An entry signal must include stop-loss and take-profit values.');
                }

                $riskDistance = Number::of($entryPrice)
                    ->sub($decision->stopLoss)
                    ->abs();
                if ($riskDistance->compare(0) <= 0) {
                    throw new UnexpectedValueException('Cannot size an order when entry and stop-loss prices are equal.');
                }

                if (! is_numeric($decision->quantity) || Number::of($decision->quantity)->compare(0) <= 0) {
                    throw new UnexpectedValueException('An entry signal must include a positive quantity.');
                }

                $quantityMultiplier = $quantityUnit === 'units' ? '1' : $configuredQuantityMultiplier;
                if (Number::of($quantityMultiplier)->compare(0) <= 0) {
                    throw new UnexpectedValueException("A {$quantityUnit}-based market pair must have a positive quantity multiplier.");
                }

                $positionMultiplier = $quantityUnit === 'lot' ? $quantityMultiplier : '1';
                $pnlMultiplier = $quantityUnit === 'contract' ? $quantityMultiplier : '1';
                $requestedPositionSize = Number::of($decision->quantity)->mul($positionMultiplier);
                $riskAmount = $riskDistance->mul($requestedPositionSize)->mul($pnlMultiplier);
                $order = new BacktestOrderHistoryEntry(
                    id: $orderHistory->count() + 1,
                    signal: $decision->signal->value,
                    openDate: $candle->price_date->format('Y-m-d H:i:s'),
                    entryPrice: $entryPrice,
                    entryPriceSource: $entry->second(),
                    stopLoss: $decision->stopLoss,
                    takeProfit: $decision->takeProfit,
                    strategyQuantity: $decision->quantity,
                    quantityUnit: $quantityUnit,
                    quantityMultiplier: (float) $quantityMultiplier,
                    pnlMultiplier: (float) $pnlMultiplier,
                    requestedRiskAmount: (float) $riskAmount->value(),
                    positionSize: 0.0,
                    requestedPositionSize: (float) $requestedPositionSize->value(),
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
                    $entry->second(),
                );
                $entryCosts = $this->transactionCosts($order, $fillQuantity, $entryPrice, $config, 'entry');
                $order->recordEntryTradeCosts($entryCosts);
                $currentCapital = (float) Number::of($currentCapital)
                    ->sub($this->sumCosts($entryCosts))
                    ->value();
                if ($config->partialFill->enabled && $config->partialFill->unfilledOrderPolicy === UnfilledOrderPolicy::Cancel) {
                    $order->cancelledEntryQuantity += $order->remainingEntryQuantity;
                    $order->remainingEntryQuantity = 0.0;
                }
                $orderHistory = $orderHistory->append($order);
                $openOrderIndex = $orderHistory->count() - 1;
            }

            $context->currentCapital = $currentCapital;
            $decisionIterator->next();
        }

        return new SpecificPairBacktestResult(
            decisions: $results,
            orders: $orderHistory,
            initialCapital: $config->initialCapital,
            quantityUnit: $quantityUnit,
            unitsPerLot: $quantityUnit === 'lot' ? (float) $configuredQuantityMultiplier : 1.0,
            quantityMultiplier: $quantityUnit === 'units' ? 1.0 : (float) $configuredQuantityMultiplier,
            endingCapital: $currentCapital,
            executionConfiguration: new BacktestExecutionConfiguration($config->slippage, $config->partialFill, $config->tradeCosts),
        );
    }

    /**
     * @return array<string, float>
     */
    private function transactionCosts(
        BacktestOrderHistoryEntry $order,
        float $positionQuantity,
        float $price,
        SpecificPairTestConfig $config,
        string $side,
    ): array {
        if (! $config->tradeCosts->enabled) {
            return [];
        }

        $costs = [];
        $tradeQuantity = $order->quantityUnit === 'lot'
            ? (float) Number::of($positionQuantity)->div($order->quantityMultiplier)->value()
            : $positionQuantity;
        $transactionValue = (float) Number::of($positionQuantity)
            ->mul($price)
            ->mul($order->pnlMultiplier)
            ->value();
        foreach (['commission', 'exchange_fee', 'clearing_fee', 'regulatory_fee', 'transaction_tax'] as $name) {
            $component = $config->tradeCosts->components()[$name];
            if ($component->appliesTo !== 'both' && $component->appliesTo !== $side) {
                $costs[$name] = 0.0;

                continue;
            }
            $isOrderCost = $component->method !== 'per_order'
                || ! $order->orderCostAlreadyCharged($name, $side);
            $costs[$name] = $this->tradeCostCalculator->transaction(
                $component,
                $tradeQuantity,
                $transactionValue,
                $isOrderCost,
            );
            if ($component->enabled && $component->method === 'per_order' && $isOrderCost) {
                $order->markOrderCostCharged($name, $side);
            }
        }

        return $costs;
    }

    /** @param array<string, float> $costs */
    private function sumCosts(array $costs): Number
    {
        return array_reduce(
            $costs,
            static fn (Number $total, float $cost): Number => $total->add($cost),
            Number::of(0),
        );
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
            $previous = $order->closestToStopLoss->getOrNull();

            if ($previous === null || $distance < $previous->distance->getOrElse(0.0)) {
                $priceSource = StressTestPriceSource::tryFrom($source);
                if ($priceSource === null) {
                    throw new UnexpectedValueException("Unsupported backtest price source: {$source}.");
                }
                $order->closestToStopLoss = Option::some(new BacktestPriceObservation(
                    $price,
                    StressTestTimestamp::fromPersistedValue(
                        $candle->price_date->format('Y-m-d H:i:s'),
                        'backtest observation date',
                    )->getOrElseThrow(new \LogicException('A valid candle timestamp was expected.')),
                    $priceSource,
                    Option::some($distance),
                ));
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
            $previous = $order->closestToTakeProfit->getOrNull();

            if ($previous === null || $distance < $previous->distance->getOrElse(0.0)) {
                $priceSource = StressTestPriceSource::tryFrom($source);
                if ($priceSource === null) {
                    throw new UnexpectedValueException("Unsupported backtest price source: {$source}.");
                }
                $order->closestToTakeProfit = Option::some(new BacktestPriceObservation(
                    $price,
                    StressTestTimestamp::fromPersistedValue(
                        $candle->price_date->format('Y-m-d H:i:s'),
                        'backtest observation date',
                    )->getOrElseThrow(new \LogicException('A valid candle timestamp was expected.')),
                    $priceSource,
                    Option::some($distance),
                ));
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
        $previousAdverse = $order->adverseExtreme->getOrNull();
        $isMoreAdverse = $previousAdverse === null || ($isLong
            ? $adverse < $previousAdverse->price
            : $adverse > $previousAdverse->price);
        if ($isMoreAdverse) {
            $priceSource = StressTestPriceSource::tryFrom($adverseSource);
            if ($priceSource === null) {
                throw new UnexpectedValueException("Unsupported backtest price source: {$adverseSource}.");
            }
            $order->adverseExtreme = Option::some(new BacktestPriceObservation(
                $adverse,
                StressTestTimestamp::fromPersistedValue($timestamp, 'backtest observation date')
                    ->getOrElseThrow(new \LogicException('A valid candle timestamp was expected.')),
                $priceSource,
                Option::none(),
            ));
        }

        $previousFavorable = $order->favorableExtreme->getOrNull();
        $isMoreFavorable = $previousFavorable === null || ($isLong
            ? $favorable > $previousFavorable->price
            : $favorable < $previousFavorable->price);
        if ($isMoreFavorable) {
            $priceSource = StressTestPriceSource::tryFrom($favorableSource);
            if ($priceSource === null) {
                throw new UnexpectedValueException("Unsupported backtest price source: {$favorableSource}.");
            }
            $order->favorableExtreme = Option::some(new BacktestPriceObservation(
                $favorable,
                StressTestTimestamp::fromPersistedValue($timestamp, 'backtest observation date')
                    ->getOrElseThrow(new \LogicException('A valid candle timestamp was expected.')),
                $priceSource,
                Option::none(),
            ));
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
     * @return Pair<Option<float>, string>
     */
    private function closePrice(BacktestOrderHistoryEntry $order, MarketPrice $candle, string $reason): Pair
    {
        if ($reason === 'stop_loss') {
            return Pair::of(Option::some($order->stopLoss), 'stop_loss');
        }

        if ($reason === 'take_profit') {
            return Pair::of(Option::some($order->takeProfit), 'take_profit');
        }

        return $this->marketOpenPrice($order, $candle);
    }

    /**
     * @return Pair<Option<float>, string>
     */
    private function marketOpenPrice(BacktestOrderHistoryEntry $order, MarketPrice $candle): Pair
    {
        $isLong = $order->signal === StrategySignal::Long->value;
        $quote = $isLong ? $candle->bid_open : $candle->ask_open;
        $source = is_numeric($quote) ? ($isLong ? 'bid_open' : 'ask_open') : 'open';
        $open = is_numeric($quote) ? $quote : $candle->open;

        return Pair::of(
            is_numeric($open) ? Option::some((float) $open) : Option::none(),
            $source,
        );
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
     * @return Pair<float, string>
     */
    private function entryPrice(MarketPrice $candle, StrategySignal $signal): Pair
    {
        $quoteName = $signal === StrategySignal::Long ? 'ask_open' : 'bid_open';
        $quote = $candle->{$quoteName};

        if (is_numeric($quote)) {
            return Pair::of((float) $quote, $quoteName);
        }

        if (is_numeric($candle->open)) {
            return Pair::of((float) $candle->open, 'open');
        }

        throw new UnexpectedValueException("Cannot open an order because the candle has no usable {$quoteName} price.");
    }

    private function isActive(MarketPrice $candle, SpecificPairTestConfig $config): bool
    {
        $timestamp = $candle->price_date;

        if ($config->excludedDays->contains((int) $timestamp->format('N'))) {
            return false;
        }

        return $config->activeSchedule->includes($timestamp);
    }

    /**
     * @param  iterable<int, StrategyDecision>  $decisions
     * @return Iterator<int, StrategyDecision>
     */
    private function decisionIterator(iterable $decisions): Iterator
    {
        if ($decisions instanceof Iterator) {
            return $decisions;
        }

        if ($decisions instanceof Traversable) {
            return new IteratorIterator($decisions);
        }

        if (is_array($decisions)) {
            return new ArrayIterator($decisions);
        }

        throw new UnexpectedValueException('Strategy decisions must be provided as an iterator.');
    }
}
