# Backtesting fundamentals

Nikaido's backtest is a historical simulation: it applies the strategy
currently wired into the application to stored market candles, then simulates
orders and position changes using configured execution rules. It is not a
connection to a broker and does not replay exchange-level messages or observe
live liquidity.

The engine is event-driven at the simulation level: it advances chronologically
through candles and applies relevant order, fill, exit, and capital changes as
they occur in that simulated timeline. This does not mean the application uses
a general event bus. In shared-capital multi-pair runs, each pair is simulated
independently first, then its recorded events are replayed together by
timestamp; this is not one live-interleaved strategy. See
[the engine lifecycle](../features/backtest-engine.md#execution-lifecycle) and
[shared-capital simulation](../features/shared-capital.md).

This page explains the model behind a run. For setup and operating steps, see
[Running backtests](../user-guide/running-backtests.md). For exact processing
rules, see the [backtest engine feature page](../features/backtest-engine.md)
and [application flow](../architecture/application-flow.md).

## From candles to results

For a selected pair, timeframe, and time window, Nikaido loads the matching
stored candles in timestamp and row-ID order. It can also load up to 200
earlier candles to warm up the current moving-average strategy. It does not
resample a timeframe, synthesize missing bars, or convert timestamp zones.
Data selection details are in [Market data](../user-guide/market-data.md).

The injected Strategy produces one decision for each loaded candle. In the
current application it delegates to a moving-average crossover with a
200-period trend filter. A decision can request `Long`, `Short`, `None`, or
`Close`; a long/short signal includes a requested quantity, stop, and target,
and can optionally specify a limit entry. The strategy does not submit a real
order. The Backtest process interprets the decision using its candle-based
entry, exit, fill, cost, and capital rules.

The process records decisions, one position-level order record per entry,
entry/exit fills, costs, and realized capital movements. Multiple partial
fills can belong to that one order record. The result analysis then derives
closed-trade summaries and optional post-run analyses. An open position at
the end of the data window is not automatically liquidated and has no
completed trade P&L.

```mermaid
flowchart LR
    Data["Stored candles<br/>pair + timeframe + window"] --> Strategy["Strategy decisions<br/>one per candle"]
    Strategy --> Simulation["Candle-based execution<br/>orders, fills, exits, costs"]
    Simulation --> Records["Decision/order history<br/>realized capital changes"]
    Records --> Analysis["Metrics and optional<br/>post-backtest analyses"]
```

## Historical simulation is not live trading

A run is conditional on a fixed dataset, strategy implementation, initial
capital, schedule, and execution configuration. If these inputs and ordering
are unchanged, a single-pair simulation is deterministic. That does not make
the simulated fills equivalent to live fills:

- Candle OHLC does not reveal the sequence of prices inside a bar. If a stop
  and target are both reached in one candle, Nikaido chooses the stop first.
- A stop or target that is reached is filled at its configured level, then
  configured slippage is applied; a gap-through fill is not reconstructed
  from tick data.
- Market decisions can be generated using the candle close while the market
  entry is priced from that same candle's open. This timing can use
  information that would not have been available at the open.
- Quote prices fall back to general OHLC when unavailable. No spread is
  invented when only general OHLC is present.
- Fixed partial fills use a configured quantity ratio. The optional volume
  mode uses only candles explicitly marked with traded volume; it does not
  treat tick volume as traded quantity or model order-book liquidity.

These are material simulation assumptions, not implementation details to
ignore when interpreting a result. Order-level rules are described in
[Order and execution semantics](order-and-execution-semantics.md).

## Strategy, execution, and research boundary

Strategy decisions and execution assumptions are separate. The wired strategy
defines signal, stop/target, and requested size; the process defines how those
instructions are tested against historical prices. Fixed slippage, partial
fills, bid/ask selection, and configurable transaction or holding costs
affect simulated execution but do not alter the supplied candle history.
Strategy parameters and the current strategy choice are not selectable in the
Workbench; see the [strategy guide](../user-guide/strategies.md).

Market-type runs execute independent pair histories first. Shared-capital
allocation later replays their recorded events in timestamp order. It is not
one strategy observing synchronized markets while making decisions. See
[Capital and risk](capital-and-risk.md) and
[multi-pair backtesting](../features/multi-pair-backtesting.md).

## Fundamental data management

The Workbench includes a Fundamental feature for manually managing economic
or company entities and their historical indicator observations. A
fundamental entity (for example, a national economy or a company) can be
linked to one or more market pairs. This is a separate relationship from the
Market Pair feature's related-pair dependencies: it links an entity to the
pair whose strategy should be able to read its observations. Only entities
linked directly to the primary pair being backtested are added to that run's
strategy context. Linking an entity only to a related pair does not make it
available to the primary pair's strategy. The feature does not
provide an external data provider, financial-statement importer, economic
calendar, or strategy selector.

The schema separates:

- `fundamentals`: entity name, optional description, and category.
- `fundamental_contents`: indicator `code` and display `name`, JSON `value`,
  observation-period `datetime`, and optional publication time `released_at`.
- `fundamental_related`: the many-to-many relationship between an entity and
  market pairs.

An indicator can have multiple dated observations. A unique constraint
prevents duplicate `(fundamental_id, code, datetime)` periods. JSON values may
be scalars or objects. The Workbench stores full timestamps; month names are
only presentation.

### Point-in-time availability

`datetime` is the period represented by an observation; it does not mean that
the value was known to the market at that time. The strategy can only see
observations with a non-null `released_at` no later than its query time. An
observation with no release timestamp remains stored and visible in the
Workbench, but is deliberately excluded from the strategy data. There is no
fallback that treats its period date as a release date.

At backtest initialization, Nikaido loads the pair's linked fundamentals and
observations released during the configured range, plus the latest prior
observation period for each indicator that was already released before the
start. This provides a warm-start value without loading the entire lifetime
history. The data is passed once into `StrategyExecutionContext`; strategy
access does not query the database per candle. The context offers:

```php
$series = $context->fundamentals()->get($fundamentalId);
$observations = $series?->observations('CPI', $currentTime) ?? [];
$latest = $series?->latestAvailable('CPI', $currentTime);
$periodValue = $series?->valueAt('CPI', $periodDate, $currentTime);
$available = $series?->has('CPI', $currentTime) ?? false;
```

`observations($code, $knownAt)` returns the eligible observations loaded for
the backtest range for one indicator. `latestAvailable()` chooses the
observation with the latest observation
period among those already released by `$currentTime`; `valueAt()` matches an
exact observation timestamp and returns the most recently released match.
Absent entities or codes return `null`, `false`, or an empty list rather than
an inferred or interpolated value. The context intentionally does not expose
all observations before the range; it keeps the latest prior period per
indicator and every eligible observation in the range.

The current schema stores one value per indicator and observation timestamp;
it does not model source provenance, revisions, or point-in-time vintages.
When importing revised series manually, overwriting an earlier value can
introduce look-ahead bias in historical research. Preserve vintages in a
future schema extension before relying on revised data. Also normalize
indicator units and identifiers consistently; Nikaido does not infer them.
See [Strategies](../user-guide/strategies.md) for the execution context and
example usage.

The resulting statistics describe the selected historical sample under the
implemented model. They do not establish that a strategy has a durable edge,
prove that data is complete or representative, or forecast future returns.
Review data provenance, warm-up, quote coverage, costs, exposure, and
limitations before drawing conclusions.
