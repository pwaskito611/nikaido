# Strategies

## What is currently wired into the backtest

The Workbench does not currently offer a strategy selector, plugin registry,
discovery mechanism, or strategy-parameter form. A backtest receives the
`App\Platform\Strategy\Strategy` service injected into
[`SpecificPairBacktestProcess`](../../laravel/app/Feature/Backtest/Process/SpecificPairBacktestProcess.php).
That service delegates to
[`main()`](../../src/main.php), the strategy function currently used by the
application. Its parameters
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
[`MarketPrice`](../../laravel/app/Feature/MarketPrice/Model/MarketPrice.php) candles
and a [`StrategyExecutionContext`](../../src/Strategy/Models/StrategyExecutionContext.php).
The strategy returns one
[`StrategyDecision`](../../src/Strategy/Models/StrategyDecision.php)
for each candle, in the same order. The process checks this one-for-one
invariant and raises an error if the strategy stops early. The current
implementation materializes close values before producing decisions, so
strategy evaluation is not an incremental streaming callback.

[`src/main.php`](../../src/main.php) contains an illustrative moving-average
strategy, not a required strategy for the application. Replace its logic and
adjust the `src/` structure to fit your strategy while preserving the existing
`main(iterable $candles, StrategyExecutionContext $context)` entry point and
one-decision-per-primary-candle contract.

The signal is a `StrategySignal` enum: `Long`, `Short`, `None`, or `Close`.
An entry (`Long` or `Short`) must supply a positive quantity and both a stop
and a take-profit price. A non-null `limitPrice` requests a limit entry; if it
is null, the entry is a market entry. `None` does not request an action.
`Close` asks the engine to close an existing position; it does not create a
short position by itself. The engine owns fill timing, quote selection,
partial-fill behavior, costs, order history, and P&L.

The backtest builds one `StrategyExecutionContext` for the primary pair and
passes that same object to the strategy for the run. Its fields are:

| Context value | Meaning |
|---|---|
| `currentCapital` | Starts at the run's initial capital. The engine updates it as each primary candle is simulated, so later decisions can size against the current simulated capital. |
| `quantityUnit` | The primary pair's unit: `units`, `contract`, or `lot`. |
| `quantityMultiplier` | `1` for `units`; otherwise the primary pair's configured contract size. |
| `pnlPerPriceUnit` | USD P/L for a one-price-unit move and one strategy quantity, derived from `tick_value / tick_size`. |
| `primaryMarketPairId` | ID of the pair whose candles drive decisions. |
| `primaryMarketPairSymbol` | Primary pair symbol in `BASE/QUOTE` form. |
| `primaryTimeframeTypeId` | Timeframe ID selected for the primary backtest candles. |
| `relatedMarketPairs` | Map of explicitly related pair series, keyed by related market-pair ID. |
| `fundamentals()` | Collection of Fundamental entities linked directly to the primary pair, keyed by Fundamental ID. |
| `cashFlows()` | When enabled for the run, per-pair cash-flow events, filtered by `available_at` through `availableAt($knownAt)`. |
| `news()` | Pair-specific news and impact assessments, filtered by `available_at` through `availableAt($knownAt)` or by their configured impact window through `impactActiveAt($knownAt)`. |

The context does not carry every backtest setting. Date range, active schedule,
slippage, partial-fill rules, trade costs, and execution behavior remain owned
by the backtest engine. Related series are represented by a
[`RelatedMarketPairData`](../../src/Strategy/Models/RelatedMarketPairData.php)
with `marketPairId`, `base`, `quote`, `timeframeTypeId`,
`candlesByTimestamp`, and `candleAt($timestamp)`. The map key is the related
market pair ID, not its symbol or timeframe. A pair configured at another
timeframe can include the primary pair ID as a related series. For example:

```php
$gbpUsd = $context->relatedMarketPairs[$relatedPairId] ?? null;
$alignedGbpUsdCandle = $gbpUsd?->candleAt($primaryCandle->price_date);
```

For an example configuration, a backtest can use EUR/GBP at 1h as its primary
series while the Market Pair form configures EUR/GBP at 1d and GBP/USD at 1d
as related series. The corresponding 1d candles can be retrieved separately:

```php
$eurGbpDaily = $context->relatedMarketPairs[$eurGbpPairId] ?? null;
$gbpUsdDaily = $context->relatedMarketPairs[$gbpUsdPairId] ?? null;

$eurGbpDailyCandle = $eurGbpDaily?->candleAt($primaryCandle->price_date);
$gbpUsdDailyCandle = $gbpUsdDaily?->candleAt($primaryCandle->price_date);
```

`candleAt()` returns `null` when that pair has no observation at the requested
timestamp. It never substitutes the nearest candle. Each related series
contains its configured timeframe's candles within the backtest range plus
up to 200 preceding warm-up candles, and retains each candle's own timestamp.
A null related timeframe uses the primary backtest timeframe.

Related pairs are directional data dependencies only; their candles do not
produce additional strategy decisions or orders. The primary pair's candle
iterable still determines the number and order of decisions. The engine applies the pair's quantity step and minimum, and rejects entries
whose initial margin exceeds available USD capital. It does not impose a
maintenance-margin, forced-liquidation, or independent maximum-position rule.
Market-type runs process each pair independently, then optionally synchronize
risk and initial-margin allocation across pair results.

The context makes related data available; it does not automatically combine,
align, or apply that data to strategy decisions. The example `main()` currently
uses only primary-pair candles for its moving-average decisions. To use related
series, add explicit logic to your strategy and handle `null` lookups when
observations do not share an exact timestamp. Each pair can be configured with
one related timeframe; related data is never forward-filled or resampled.
See [Market data](market-data.md#related-market-pair-data) for configuration
and data-loading behavior.

## Fundamental observations

The context also exposes preloaded, point-in-time fundamental observations
through `fundamentals()`. A Fundamental must be linked to the primary pair in
the Fundamental form; linking it only to one of the primary pair's related
pairs does not include it in this context. Each included entity is keyed by
its Fundamental ID. Observations without a `released_at` timestamp are
retained in the Workbench but are not passed into strategy data. At each
decision, use the primary candle's timestamp as the knowledge boundary:

```php
$economy = $context->fundamentals()->get($fundamentalId);
$latestCpi = $economy?->latestAvailable('CPI', $primaryCandle->price_date);
$cpiForPeriod = $economy?->valueAt(
    'CPI',
    new DateTimeImmutable('2026-08-01'),
    $primaryCandle->price_date,
);
```

Related candles and Fundamental observations are independent inputs. This
example reads an exact-time related candle and the latest CPI observation
that was publicly released by the current primary candle:

```php
$relatedSeries = $context->relatedMarketPairs[$relatedPairId] ?? null;
$relatedCandle = $relatedSeries?->candleAt($primaryCandle->price_date);

$economy = $context->fundamentals()->get($fundamentalId);
$cpi = $economy?->latestAvailable('CPI', $primaryCandle->price_date);
$cpiValue = $cpi?->value;
```

Either lookup can be absent. Handle `null` explicitly; neither related prices
nor fundamental values are filled, interpolated, or inferred for the
strategy.

`observations($code, $knownAt)` returns the eligible observations loaded for
the backtest range for one indicator, including the latest prior observation
period before that range. `latestAvailable()` returns the latest observation
period released
by `$knownAt`; `valueAt()` matches an exact observation timestamp, and
`has()` checks whether any eligible observation exists. A missing entity or
value stays missing: there is no interpolation, forward-fill, or fallback
from period date to release date. The relevant linked observations are loaded
before replay, so these lookups do not issue database queries per candle. The
fundamental schema does not yet retain data revisions or provider provenance;
avoid overwriting revised historical values if point-in-time vintages matter.
See [Backtesting fundamentals](../concepts/backtesting-fundamentals.md) for
the release-time policy and schema details.

## News

News is linked to a market pair through an impact assessment. The backtest
preloads the primary pair's linked news; no database query is made from the
strategy. Use the candle timestamp as the knowledge boundary:

```php
$availableNews = $context->news()->availableAt($primaryCandle->price_date);
$activeImpacts = $context->news()->impactActiveAt($primaryCandle->price_date);

foreach ($activeImpacts as $item) {
    $directionalImpact = $item->impactScore; // -1 bearish, 0 neutral/unknown, +1 bullish
}
```

`available_at` is the earliest time a strategy can see an article and cannot
precede `published_at`. The impact window starts at
`published_at + impactLagSeconds` and ends after `impactDurationSeconds`, but
is not exposed as active before `available_at`. The loader includes articles
published before the requested backtest range when their impact windows
overlap the range. Impact scores and confidence are annotations only: the
engine does not automatically alter orders or P/L based on them. See the
[News user guide](news.md) for manual management and API import.

## Application data and tests

For an application backtest, the loader reads the configured data before it
calls `main(iterable $candles, StrategyExecutionContext $context)`. Strategy
code in `src/main.php` consumes those arguments; it should not query Eloquent
models or repositories. Context lookups use preloaded data, so they do not
issue database queries per candle.

| Workbench setting | Data supplied to the strategy |
|---|---|
| Selected primary pair, timeframe, and range | Chronological primary candles through `$candles`, including up to 200 earlier warm-up candles. |
| Primary pair's **Related Market Pairs** selections | Each configured pair/timeframe candle series in `$context->relatedMarketPairs`, keyed by pair ID. Use `candleAt()` for exact timestamp lookups. |
| Fundamental's **Related market pairs** selections | Released observations for Fundamental entities linked directly to the primary pair, accessed through `$context->fundamentals()`. |
| News items linked to the primary pair | Articles known by each candle timestamp, accessed through `$context->news()`. |

Related candle series and Fundamental observations do not produce additional
decisions. `main()` still returns one decision per primary candle. The
relationship behavior and timestamp limitations are described in
[Market data](market-data.md) and
[Backtesting fundamentals](../concepts/backtesting-fundamentals.md).

For a unit test, call the pure strategy entry point with in-memory candle
fixtures and a constructed context; no database setup is needed:

```php
$decisions = \Nikaido\Strategy\main($fixtureCandles, $fixtureContext);
```

The application adapter and backtest process supply these arguments during a
real run. A missing or non-numeric close is rejected by `src/main.php`, and
fewer than 200 closes produce `None` decisions with the current example
strategy.

## Performance and native acceleration

For strategies with large numerical loops or expensive calculations, first
profile a representative backtest and identify the part that dominates total
runtime. Prefer algorithmic improvements, avoiding repeated work, or batching
calculations in PHP when those address the bottleneck. A complex set of
business rules alone is not a reason to use native code.

If profiling still shows a CPU-bound numerical kernel dominating runtime,
consider implementing that isolated kernel in C++ and calling it through FFI.
Keep strategy orchestration, context access, validation, and construction of
`StrategyDecision` objects in PHP. Use a narrow C-compatible interface and
make coarse calls that pass a whole series or batch; calling across FFI once
per value or candle can make data-copying overhead outweigh the computation.

Before adopting it, compare end-to-end runtime and memory against the PHP
implementation, including conversion and copy costs. Check native output
against deterministic fixtures for candle ordering, warm-up behavior, and
one decision per primary candle. Plan for building and shipping the library,
the PHP FFI extension, and the `ffi.enable` restrictions of the target SAPI.
See [C++ and PHP FFI for research and production](../reference/php-research-ecosystem.md#c-mathematical-ecosystem-and-php-ffi-for-research-and-production)
for the integration, safety, and deployment trade-offs.

## Modifying and testing strategy behavior

There is no public strategy interface to implement and no UI setting that
selects a different class. The current service is injected by Laravel and the
default strategy is its concrete behavior. To change what application
backtests execute, update the existing strategy wiring/implementation and add
tests around the decision rules and the backtest process. Do not assume that
adding a new class or setting an environment variable will register it.

The `Nikaido\Strategy\main()` function can be tested independently with
ordered candle fixtures and a `StrategyExecutionContext`. Test at least:

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
