# Market types and timeframes

## Purpose and actual semantics

Market types group market pairs; timeframe types label groups of candles.
Neither record implements a market's trading mechanics or a calendar. A
market-type backtest uses the type to select eligible pairs, then uses the
selected timeframe ID to load that pair's rows. The engine does not parse
timeframe names or resample prices.

## Fields, identifiers, and validation

| Entity | Stored values | Validation and uniqueness |
|---|---|---|
| Market type | Auto-increment ID; `name` up to 50 characters | Required, trimmed, unique name; database unique index |
| Timeframe | Auto-increment ID; `name` up to 50 characters | Required, trimmed, unique name; database unique index |
| Market pair | ID; `market_type_id`; base/quote; quantity unit; contract size | Existing market type; trimmed uppercase base and quote, 1–10 chars; unique base/quote per market type at request level |

The UI formats IDs for display (for example `MT-0001` and `TF-0001`); these
labels are not separately stored keys. Pair symbols are also user labels,
not validated against an external instrument catalog. Pair symbol uniqueness
is request-level and is not backed by a composite database unique index.
Likewise, the market-price pair/timeframe/timestamp uniqueness rule is
request/import logic rather than a database composite constraint.

Timeframe names are free-form aside from required, trimmed, unique, and
maximum length. This is intentional for local/manual or push candle sets, but
the Alpha Vantage connector supports only specific labels. Its provider
mapping is detailed in [Data ingestion and validation](data-ingestion-and-validation.md).

## Relationships and deletion

A market type has many pairs. A pair belongs to one market type and has many
market prices. A timeframe has many market prices. Market prices belong to
one pair and one timeframe. Import sessions also refer to a pair and a
timeframe. These are Eloquent relationships and scalar ID columns; core
migrations do not add database foreign keys to enforce them.

The Workbench blocks deleting a market type with pairs, a pair with price
history or any import-session reference, and a timeframe with price records
or any import-session reference. Stopped import sessions continue to block
deletion. There is no cascade deleting candles or sessions. Direct SQL deletion
can bypass application guards and leave orphan records.

Editing names preserves identifiers and leaves pair/price/import rows
attached. Changing pair symbols or quantity metadata retains the pair ID, so
the same stored candles may be interpreted/displayed using changed metadata
in future runs. Saved run JSON is not recalculated when metadata changes.

## Backtest and import interactions

Single-pair runs select a specific pair and timeframe ID. Market-type runs
include pairs of the chosen type that have at least one candle for the
selected timeframe inside the date range; excluded pair IDs are skipped.
Rows are traversed by timestamp then database ID. The timeframe label does
not cause interval verification, timezone conversion, or resampling.

The import page uses the pair and timeframe selectors for both provider pulls
and push receiver sessions. External Alpha Vantage integration converts the
timeframe name to one of its known interval mappings; an otherwise valid
timeframe name may be unsupported for that connector. A push session binds
the pair/timeframe IDs directly and accepts timestamps using the API's
specified date format, without checking interval spacing.

## Operations, errors, and verification

Use **Market Types** and **Timeframes** in the Workbench to list, show, create,
edit, or delete records. Invalid/duplicate names produce field validation
errors; deletion of referenced records returns an error on the index page.
The user-facing steps and forms are in
[Market Types and Timeframes](../user-guide/market-types-and-timeframes.md).

Relevant implementations include the
[`MarketTypeRequest`](../../app/Feature/MarketTypes/Request/MarketTypeRequest.php),
[`TimeframeTypeRequest`](../../app/Feature/Timeframes/Request/TimeframeTypeRequest.php),
and
[`MarketPairRequest`](../../app/Feature/MarketPairs/Request/MarketPairRequest.php).
Related: [Market support](market-support.md),
[Market data management](market-data-management.md), and
[Data Management](../user-guide/data-management.md).
