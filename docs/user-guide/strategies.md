# Strategies

## What is currently wired into the backtest

The Workbench does not currently offer a strategy selector, plugin registry,
discovery mechanism, or strategy-parameter form. A backtest receives the
`App\Platform\Strategy\Strategy` service injected into
[`SpecificPairBacktestProcess`](../../app/Feature/Backtest/Process/SpecificPairBacktestProcess.php).
That service delegates to
[`GenerateMovingAverageCrossDecisionsAction`](../../app/Platform/Strategy/Actions/GenerateMovingAverageCrossDecisionsAction.php),
the strategy implementation currently used by the application. Its parameters
are code constants, not per-run settings:

- Fast EMA: 20 closes
- Slow EMA: 50 closes
- Trend moving average: 200 closes
- Target distance: 2 times the entry-to-stop distance
- Requested risk: 1% of the context's current capital

The run loads up to 200 candles before the selected start date as indicator
warm-up. The backtest only records decisions in the requested window, but the
strategy receives the warm-up candles as preceding history.

## Execution flow and decision contract

The process supplies an ordered iterable of
[`MarketPrice`](../../app/Feature/MarketPrice/Model/MarketPrice.php) candles
and a [`StrategyExecutionContext`](../../app/Platform/Strategy/Models/StrategyExecutionContext.php).
The strategy returns one
[`StrategyDecision`](../../app/Platform/Strategy/Models/StrategyDecision.php)
for each candle, in the same order. The process checks this one-for-one
invariant and raises an error if the strategy stops early. The current
implementation materializes close values before producing decisions, so
strategy evaluation is not an incremental streaming callback.

The signal is a `StrategySignal` enum: `Long`, `Short`, `None`, or `Close`.
An entry (`Long` or `Short`) must supply a positive quantity and both a stop
and a take-profit price. A non-null `limitPrice` requests a limit entry; if it
is null, the entry is a market entry. `None` does not request an action.
`Close` asks the engine to close an existing position; it does not create a
short position by itself. The engine owns fill timing, quote selection,
partial-fill behavior, costs, order history, and P&L.

The context exposes `currentCapital`, `quantityUnit`, and
`quantityMultiplier`. `currentCapital` is updated by the backtest as its
simulation proceeds; the strategy uses it to size each new risk request. The
engine does not automatically cap the strategy's quantity to a notional,
margin, or maximum-position limit.

## Example: execute the strategy that is actually provided

This standalone example uses the actual service and types; it does not create
a new extension point. In application code, `$candles` must be a chronological
iterable of candles for one pair and timeframe, including sufficient
pre-window warm-up data. The example reads a complete series for clarity; a
production test should select only the required history.

```php
use App\Feature\MarketPrice\Model\MarketPrice;
use App\Platform\Strategy\Actions\GenerateMovingAverageCrossDecisionsAction;
use App\Platform\Strategy\Models\StrategyExecutionContext;
use App\Platform\Strategy\Strategy;

$pairId = 1;
$timeframeTypeId = 1;

$candles = MarketPrice::query()
    ->where('pair_id', $pairId)
    ->where('timeframe_type_id', $timeframeTypeId)
    ->orderBy('price_date')
    ->orderBy('id')
    ->get();

$strategy = new Strategy(new GenerateMovingAverageCrossDecisionsAction());
$context = new StrategyExecutionContext(
    currentCapital: 10_000.00,
    quantityUnit: 'units',
    quantityMultiplier: 1.0,
);

foreach ($strategy->execute($candles, $context) as $index => $decision) {
    printf(
        "%d %s quantity=%s stop=%s target=%s limit=%s\n",
        $index,
        $decision->signal->value,
        $decision->quantity ?? '-',
        $decision->stopLoss ?? '-',
        $decision->takeProfit ?? '-',
        $decision->limitPrice ?? '-',
    );
}
```

For `contract` or `lot` pairs, pass the pair's configured contract-size
multiplier rather than `1.0`. A missing or non-numeric close is rejected by the
moving-average action. Fewer than 200 closes produce `None` decisions; no trade
is created merely because the data is too short.

## Modifying and testing strategy behavior

There is no public strategy interface to implement and no UI setting that
selects a different class. The current service is injected by Laravel and the
default strategy is its concrete behavior. To change what application
backtests execute, update the existing strategy wiring/implementation and add
tests around the decision rules and the backtest process. Do not assume that
adding a new class or setting an environment variable will register it.

The strategy action can be tested independently with ordered
`MarketPrice` fixtures and a `StrategyExecutionContext`. Test at least:

- warm-up lengths below and at the indicator periods;
- long and short crossings and the trend filter;
- stop/target orientation and quantity under each quantity unit;
- `None` and `Close` signals;
- malformed or missing close values;
- one decision for every input candle.

Feature tests exercise how those decisions become historical orders. Run the
relevant Backtest tests using the project's [test instructions](../getting-started/running-tests.md).

## Important behavior and limitations

- Signals are computed from candles including their close values, but an entry
  decision can be executed at that same candle's open. The signal therefore
  has access to information that would not have been available at that open.
  This is a potential look-ahead bias in the current example flow; treat
  results accordingly. This guide documents the behavior rather than changing
  strategy timing.
- Strategy-defined risk is a sizing request, not a guarantee of realized loss.
  Slippage, gaps, partial fills, transaction costs, and execution rules can
  change actual outcomes.
- Strategy parameters and position-sizing rules are currently fixed in code.
  A backtest form cannot override the moving-average periods or risk fraction.
- The engine permits one active order/position at a time for a single pair.
  Market-type runs can have positions in different pairs.
- Keep candles sorted by timestamp and use a consistent timestamp convention.
  See [Market data](market-data.md) for selection and time assumptions.
