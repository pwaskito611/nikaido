# Data models

This reference describes the current Eloquent models and database schema from
their model definitions and migrations. The schema contains several
application-level relationships that are **not** enforced by database foreign
keys. The broader application persistence boundaries are diagrammed in the
[Architecture overview](../architecture/overview.md).

## Market-data models

| Model | Table | Columns and constraints | Eloquent relationships |
|---|---|---|---|
| [`MarketType`](../../app/Feature/MarketTypes/Model/MarketType.php) | `market_type` | `id` primary key; `name` string(50), unique. No timestamps. | `marketPairs`: has many `MarketPair` by `market_type_id`. |
| [`MarketPair`](../../app/Feature/MarketPairs/Model/MarketPair.php) | `market_pair` | `id`; `market_type_id` unsigned big integer; `base` and `quote` string(10); `contract_size` decimal(20,8), default `1`; `quantity_unit` string(16), default `lot`. Index on `market_type_id`; no database unique constraint for a pair symbol. No timestamps. | `marketType`: belongs to `MarketType`; `prices`: has many `MarketPrice` by `pair_id`. |
| [`TimeframeType`](../../app/Feature/Timeframes/Model/TimeframeType.php) | `timeframe_type` | `id` primary key; `name` string(50), unique. No timestamps. | `marketPrices`: has many `MarketPrice` by `timeframe_type_id`. |
| [`MarketPrice`](../../app/Feature/MarketPrice/Model/MarketPrice.php) | `market_price` | `id`; `pair_id` unsigned big integer; `price_date` datetime; `timeframe_type_id` unsigned big integer; `open`, `high`, `low`, `close`, `price_at_8`, and bid/ask OHLC are decimal(12,6). Market-price values are nullable in the final schema. Indexes on `pair_id` and `timeframe_type_id`; no database unique constraint on timestamp/pair/timeframe. No timestamps. | `pair`: belongs to `MarketPair`; `timeframeType`: belongs to `TimeframeType`. |

`MarketPair.contract_size` is cast to `decimal:8`. `MarketPrice.price_date`
is cast to `datetime`; its price fields are cast to `decimal:6`. Other listed
domain models do not declare custom casts. These models do not declare custom
scopes, accessors, mutators, or model event hooks.

`MarketPairRequest` and `MarketPriceRequest` enforce pair-symbol and
pair/timeframe/timestamp uniqueness during normal Workbench writes. Those
checks are validation rules, not database unique indexes; direct database
writes can bypass them, and concurrent writes are not protected by a unique
constraint. Market-type and timeframe names, by contrast, have both request
validation and database unique constraints.

The base/quote and price relationships above are Eloquent associations only:
the market-pair and market-price migrations do not add foreign keys for them.
The deletion Actions protect normal Workbench use by refusing to delete a
market type with pairs, a pair with prices or import-session references, and a
timeframe with prices or import-session references. These checks are not
database constraints and do not protect direct SQL deletion. A market-price
record is deleted directly; backtest results are not automatically recomputed.
Historical market-type run rows do not prevent deletion of a market type,
pair, or timeframe: those references have no foreign keys. Run rows keep
numeric IDs and market-type/pair name snapshots where defined. Editing any of
these market-data records does not rewrite completed run snapshots.

The initial market-price schema used one `bid` and one `ask` value. A migration
expanded these into quote OHLC fields using legacy quote and general OHLC data;
the current schema makes the bid/ask OHLC columns nullable. No database check
constraint enforces candle high/low ordering or positive values.

## Import sessions

[`DataImportSession`](../../app/Feature/DataImport/Model/DataImportSession.php)
uses table `data_import_session`: `id`, `pair_id`, `timeframe_type_id`,
`token_hash` char(64) unique, `status` string(16), `started_at`,
`stopped_at` nullable, `created_at`, and `updated_at`. There is an index on
`status` and a compound index on `pair_id`, `timeframe_type_id`, and `status`.
The model casts the three date/time fields to datetimes and belongs to a
`MarketPair` and a `TimeframeType`.

The `pair_id` and `timeframe_type_id` columns are not foreign keys. The bearer
token itself is not stored: the repository looks up its SHA-256 hash and
requires `status = active`.

## Persisted market-type runs

| Model/table | Schema and behavior |
|---|---|
| [`MarketTypeBacktestRun`](../../app/Feature/Backtest/Model/MarketTypeBacktestRun.php) / `market_type_backtest_run` | `id`, `market_type_id`, `market_type_name` string(50), `configuration` JSON, `total_pairs` unsigned integer default 0, `status` string(32), nullable `started_at`/`completed_at`, and Laravel timestamps. `configuration` is cast to array, `total_pairs` to integer, and times to datetime. Has many pair runs by `run_id`. The `market_type_id` is not a foreign key. |
| [`MarketTypeBacktestPairRun`](../../app/Feature/Backtest/Model/MarketTypeBacktestPairRun.php) / `market_type_backtest_pair_run` | `id`, `run_id`, `pair_id`, `pair_name` string(32), `status` string(32), nullable JSON `result`, nullable text `failure_message`, nullable `started_at`/`completed_at`, and Laravel timestamps. `result` is cast to array and times to datetime. Unique on `(run_id, pair_id)`; indexes cover `run_id`, `(run_id, id)`, and `status`. Belongs to a parent run by `run_id`. |

The pair-run `run_id` is the market-data-related schema's explicit foreign
key: it references `market_type_backtest_run.id` and cascades on parent-run
deletion. `pair_id` is not a foreign key. Pair results are JSON snapshots,
not live relationships to the pair's later market-data rows.

## Laravel support tables

The migrations also create Laravel support tables for cache (`cache`,
`cache_locks`), queues (`jobs`, `job_batches`, `failed_jobs`), and sessions.
The initial migration creates `sessions`; a later migration removes `users`
and `password_reset_tokens` but leaves `sessions`. The session table's
nullable `user_id` is indexed, not declared as a foreign key. The application
does not register authentication routes or user-facing authorization.

## Constraints and verification

The market-type and timeframe `name` uniqueness and the market pair/price
indexes are asserted by
[`DatabaseMigrationsTest`](../../tests/Feature/DatabaseMigrationsTest.php).
The Eloquent market-data relationships and casts are exercised by
[`DatabaseModelsTest`](../../tests/Feature/DatabaseModelsTest.php). Migrations
are the source of truth for installed schemas; an individual database can
differ if its migration history is incomplete or was changed outside the
repository.
