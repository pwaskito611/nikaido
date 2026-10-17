# Market data management

## Scope and storage relationships

This page documents the Workbench's stored-record behavior rather than
repeating the operational forms in [Data Management](../user-guide/data-management.md).
Current persisted entities include:

- `market_type`: unique display name.
- `market_pair`: `market_type_id`, base, quote, quantity unit, and contract
  size.
- `timeframe_type`: unique display name.
- `market_price`: `pair_id`, `timeframe_type_id`, timestamp, general/quote
  prices, and optional legacy `price_at_8`.
- `data_import_session`: pair/timeframe references, token hash, status, and
  start/stop timestamps.

Eloquent models expose `MarketType -> marketPairs`,
`MarketPair -> marketType/prices`, `TimeframeType -> marketPrices`, and
`MarketPrice -> pair/timeframeType` relations. However, the core migrations
declare scalar IDs and indexes rather than database foreign keys for these
relationships. Referential integrity is therefore primarily application
behavior; direct SQL or external writers can create orphan references.

## CRUD routes and persistence behavior

The resource routes use the `/workbench` prefix and
`workbench.*` route names:

| Record | UI path | Operations |
|---|---|---|
| Market type | `/workbench/market-types` | list, create, show, edit, update, delete |
| Pair | `/workbench/market-pairs` | list, create, show, edit, update, delete |
| Timeframe | `/workbench/timeframes` | list, create, show, edit, update, delete |
| Market price | `/workbench/market-prices` | list, create, show, edit, update, delete |

Create/update actions write through repositories; delete actions remove one
record after feature-specific guards. List pages are paginated (normally 15
records for metadata and 25 prices), while detail pages load related display
records and counts as needed. Market-price browsing can filter by pair and
timeframe and sorts newest timestamp first. It retains query parameters across
pages. Operational field rules are in the
[market-types/timeframes guide](../user-guide/market-types-and-timeframes.md)
and [data management guide](../user-guide/data-management.md).

Market-price manual form uniqueness checks pair, timeframe, and exact
timestamp, but there is no matching composite unique database index. Market
type and timeframe names have unique database indexes. Pair symbol uniqueness
within its market type is checked by the request but not enforced by a unique
database index. Uniqueness checks can therefore be bypassed by direct writes
and are not race-proof without database constraints.

## Deletion guards and consequences

Application deletion rules are explicit:

| Record | Deletion blocked when |
|---|---|
| Market type | Any market pair still refers to it |
| Market pair | Any price history exists, or any import session references it |
| Timeframe | Any market-price row uses it, or any import session references it |
| Market price | No feature-specific dependency guard; that row is deleted |

The pair/timeframe session-reference checks include stopped historical import
sessions, because session rows are retained. There is no cascading deletion
of prices or import sessions. Since these protections run in application code
and database foreign keys are absent, direct deletion outside those actions
can leave orphan rows.

Updating a market type or timeframe name preserves its ID and dependent
records. Pair edits may change symbol, market type, quantity unit, or
contract-size metadata while existing price rows remain attached to the same
pair ID. This changes the current pair metadata seen when those candles are
loaded; it does not rewrite prices. A timeframe rename similarly relabels
existing price rows, and backtest filtering remains by timeframe ID.

Deleting or editing market prices can change future simulation input. Saved
market-type run results contain copied configurations and pair result JSON;
they are not recomputed when source records change. Single-pair result display
uses the session workflow rather than a durable market-type-run record.
Preserve source data and run assumptions separately when reproducibility is
required.

## Consistency and failure behavior

Workbench forms validate references and field constraints before repository
writes. Deletion guards return validation errors to the relevant index page.
Missing route IDs return not-found behavior. No background integrity repair
or orphan cleanup is implemented.

The application-level pair/timeframe/date uniqueness check for a manual
market-price operation differs from import upsert behavior; see
[Data ingestion and validation](data-ingestion-and-validation.md). Use the
Market Prices filters to check for duplicate times, missing ranges, incorrect
pair/timeframe assignment, and nullable values before running a backtest.
Backtests do not repair data or verify that a user-defined timeframe label
matches the actual timestamp spacing.

## Verification and related documentation

Routes, repositories, and model relationships are defined in the relevant
`app/Feature/{MarketTypes,MarketPairs,Timeframes,MarketPrice}` modules.
Deletion restrictions for pair/timeframe import-session references are covered
by
[`DataImportFeatureTest`](../../tests/Feature/DataImportFeatureTest.php);
market-data selection and import behavior are exercised by the backtest and
import feature tests.

Related: [Data Management](../user-guide/data-management.md),
[Market Types and Timeframes](../user-guide/market-types-and-timeframes.md),
[Data ingestion and validation](data-ingestion-and-validation.md), and
[Backtest engine](backtest-engine.md).
