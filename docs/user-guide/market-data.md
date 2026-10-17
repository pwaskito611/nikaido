# Market Data

This page describes candle structure and what the backtest engine consumes.
For adding/importing records, filters, CRUD operations, validation differences,
and error handling, see [Data Management](data-management.md). Creating market
groups and interval labels is covered in
[Market Types and Timeframes](market-types-and-timeframes.md).

Nikaido backtests consume stored candles. A provider connector or accepted
upload does not certify that prices are complete, correctly adjusted, or
representative of executable market prices. Current input paths are manual
Workbench entry, the Alpha Vantage FX connector, and the authenticated
incoming-candle API; there is no CSV/Excel import or general market-data API.

## Stored candle structure

Each `market_price` row identifies a pair (`pair_id`), timeframe
(`timeframe_type_id`), and candle timestamp (`price_date`). General `open`,
`high`, `low`, `close`, plus bid/ask OHLC are decimal columns with six
fractional places. The schema permits nullable prices. The manual form
requires general `close` and permits the other general OHLC fields to be
blank; bid/ask OHLC and the legacy `price_at_8` are optional. The push API
requires all four general OHLC values and allows each bid/ask field to be
omitted. `price_at_8` is not used by the strategy or execution logic.

The input paths have different validation boundaries. The manual form
requires positive prices with at most six decimal places but does not check
candle high/low consistency. The push API allows numeric values from zero
through 999999.999999 and checks general OHLC consistency; it checks bid/ask
consistency only when all four values on one side are present. The provider
connector checks numeric OHLC structure and timestamps but does not enforce
the same high/low-consistency rule as the push API. Provide positive finite
prices and internally consistent general and quote OHLC regardless of the
input path.

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

Manual market-price validation rejects a duplicate pair/timeframe/timestamp
key, and the push/provider import paths update an existing candle for that
key. The database itself has no composite unique constraint for this key, so
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
