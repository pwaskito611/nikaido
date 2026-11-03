# Market Data

This page describes candle structure and what the backtest engine consumes.
For adding/importing records, filters, CRUD operations, validation differences,
and error handling, see [Data Management](data-management.md). Creating market
groups and interval labels is covered in
[Market Types and Timeframes](market-types-and-timeframes.md).

Nikaido backtests consume stored candles. Data can be entered manually in the
Workbench or sent to its authenticated incoming-candle API. The Data Import
page waits for the application user's sender; it does not fetch or poll a
third-party provider. Received data is not certified as complete, correctly
adjusted, or representative of executable market prices. There is no
CSV/Excel import or general market-data CRUD API.

## Stored candle structure

Each `market_price` row identifies a pair (`pair_id`), timeframe
(`timeframe_type_id`), and candle timestamp (`price_date`). General `open`,
`high`, `low`, `close`, plus bid/ask OHLC are decimal columns with six
fractional places. The schema permits nullable prices. The manual form
requires general `close` and permits the other general OHLC fields to be
blank; bid/ask OHLC and the legacy `price_at_8` are optional. The push API
requires all four general OHLC values and allows each bid/ask field to be
omitted. `price_at_8` is not used by the strategy or execution logic.

The optional `volume` column stores candle volume with a `volume_type` of
`traded` or `tick`; both fields are nullable. Manual entry and the push API
preserve this distinction, and the push API requires a type whenever volume
is supplied. Volume-based partial fills use only `traded` values and require
them to match the backtest engine's execution quantity units. Untyped volume
is unavailable, and tick volume is never treated as traded quantity.

The input paths have different validation boundaries. The manual form
requires positive prices with at most six decimal places but does not check
candle high/low consistency. The push API allows numeric values from zero
through 999999.999999 and checks general OHLC consistency; it checks bid/ask
consistency only when all four values on one side are present. Provide
positive finite prices and internally consistent general and quote OHLC
regardless of the input path.

General OHLC values are the strategy's primary candle data. The strategy
needs numeric `close` values for its indicators. The execution process uses
`open`, `high`, `low`, and `close` as price references and can use
quote-specific values when present. Missing values can prevent entry/exit
evaluation or cause an execution error; the backtest does not apply a general
gap-filling algorithm.

## Time, timeframe, and ordering

The stored timestamp is a date-time without an explicit timezone offset.
Normalize imported timestamps to one documented convention and use the same
convention for stored data and requested ranges. Nikaido does not infer a
provider timezone or convert candles between zones.

A timeframe is selected through its configured `timeframe_type_id`; the
backtest does not resample candles or verify that timestamps are spaced at
the interval implied by the timeframe's display name. Verify the interval
yourself.

The backtest loader selects the chosen pair and timeframe, orders rows by
`price_date` and then record ID, and includes up to 200 earlier candles before
the requested start for strategy warm-up. The selected window is bounded by
the requested start and end. A pair with fewer than 200 earlier candles can
still run, but the supplied strategy returns no signals until it has enough
indicator history. No rows are synthesized for missing timestamps.

## Related market-pair data

In **Workbench → Market Pairs**, create or edit the primary pair and use the
**Related Market Pairs** section to select its dependencies. Choose a timeframe
for each selected pair. Leave the timeframe as **Use backtest timeframe** to
follow the primary backtest timeframe. A related-pair dependency is stored from
the pair being edited to the selected pair; it is not automatically reciprocal
or transitive. For example, if EUR/GBP is related to GBP/USD, a backtest of
EUR/GBP can receive GBP/USD candles, but a backtest of GBP/USD does not receive
EUR/GBP candles unless that reverse relation is also configured. A relation
from A to B to C does not cause a backtest of A to load C.

For example, configure EUR/GBP as the primary pair and select:

- EUR/GBP at 1d as an additional series (the same pair can be selected when a
  related timeframe is explicitly chosen);
- GBP/USD at 1d.

A backtest of EUR/GBP at 1h then provides the primary EUR/GBP 1h candles and
the two configured 1d series to the strategy. A selected related pair is a
data dependency, not another backtest target: it does not produce extra
strategy decisions, orders, or result rows. The primary pair may also be
selected as its own related series to expose another timeframe of that same
pair; this self-relation requires an explicit timeframe. Each related pair
entry has one selected timeframe.

The loader uses the same requested date range for each series, orders each
series by its own candle timestamps, and includes up to 200 earlier candles
for warm-up. It does not convert or resample between timeframes. The actual
timeframe IDs are available in the strategy context; the display names are
configured separately in the Timeframes feature.

Related candles retain their actual timestamps. A strategy can request an
exact observation for a primary candle's timestamp; if no related candle
exists at that timestamp, the lookup returns `null`. Different sessions,
holidays, or timeframe boundaries are not forward-filled, nearest-matched,
or otherwise synchronized. Check data coverage for every configured
pair/timeframe combination.

Related market pairs and related Fundamentals are separate relationships.
The pair-to-pair relation supplies additional candle series. To link dated
Fundamental observations, open **Workbench → Fundamentals**, create or edit a
Fundamental, and select pairs under **Related market pairs**. A pair backtest
receives only Fundamental entities linked directly to its primary pair;
linking an entity only to a related pair does not make it available to the
primary strategy. See
[Strategies](strategies.md#fundamental-observations) and
[Backtesting fundamentals](../concepts/backtesting-fundamentals.md) for the
context access methods and release-time rules.

Related-pair loading uses the same candle source and price fields described
above. It does not certify that observations are complete or suitable as
executable prices. See [Strategies](strategies.md#execution-flow-and-decision-contract)
for the strategy access contract and example.

Manual market-price validation rejects a duplicate pair/timeframe/timestamp
key, and the push API updates an existing candle for that key. The database
itself has no composite unique constraint for this key, so
direct writers may still create duplicates. If duplicates exist, the
backtest's ID tie-break makes processing order deterministic but does not make
duplicate market data valid.

## Bid/ask use and fallback

When quote-specific values are present, the engine uses them as follows:

- long market entry: `ask_open`;
- short market entry: `bid_open`;
- long exits: bid-side values;
- short exits: ask-side values;
- buy-limit trigger: `ask_low`;
- sell-limit trigger: `bid_high`.

If the relevant quote value is missing or non-numeric, the engine falls back
to the corresponding general OHLC value (`open`, `low`, `high`, or `close`).
It does not synthesize a spread or midpoint. When bid/ask data is absent, the
general candle prices are used as-is, so there is no modeled spread unless
the input general prices already embed one.

## Check coverage before a backtest

Use the Market Prices view filtered to the exact pair and timeframe. Check:

1. earliest and latest timestamps cover the selected range;
2. at least 200 earlier candles are available if using the current
   moving-average strategy;
3. timestamps are unique, ordered, and consistently zoned;
4. the general OHLC values are complete, positive, and internally plausible;
5. bid/ask data is either complete enough for the intended simulation or
   intentionally absent; and
6. the configured timeframe matches the actual spacing in the history.

A successful run with zero orders is different from a failed data load. Zero
orders can result from a flat or non-crossing series, insufficient warm-up,
active hours/days excluding signal candles, invalid stop/target geometry, or
the strategy's fixed trend filter. Inspect loaded coverage and strategy
decisions before attributing the result to the execution engine. For the
data-loading walkthrough, see
[First backtest](../getting-started/first-backtest.md).

For technical price-column, quote-fallback, and timeframe semantics see
[Market support](../features/market-support.md); for operational entry and
import details, see [Data Management](data-management.md).
