# Data ingestion and validation

## Supported ingestion paths

Nikaido currently has three candle-ingestion paths:

1. Manual create/update forms under `/workbench/market-prices`.
2. An on-demand Alpha Vantage FX pull from `/workbench/data-import`.
3. A bearer-token receiver at `POST /api/data-import/candles`, enabled by
   starting a pair/timeframe session in the Data Import page.

There is no CSV/Excel uploader, scheduled provider sync, public market-price
CRUD JSON API, or general-purpose file import command. Supported user
workflows and examples are in [Data Management](../user-guide/data-management.md).

## Manual entry and form validation

`MarketPriceRequest` validates both creates and updates. `pair_id` and
`timeframe_type_id` must exist. `price_date` is required in `Y-m-d H:i:s`
format; the request also accepts the browser's `Y-m-d H:i` value (with `T`
separator or space) by normalizing it to seconds. The combination
`pair_id + timeframe_type_id + price_date` is checked for uniqueness while
excluding the edited record.

General `close` is required. General `open`, `high`, `low`, and `price_at_8`
are nullable. Eight quote fields (`bid_*`, `ask_*`) are nullable. Every
non-null price must be numeric, greater than zero, and have at most six
decimal places. These request rules do not enforce candle high/low
consistency, bid below ask, or completeness of any bid/ask side. This is an
important distinction from pushed-candle validation.

The corresponding create/update action passes validated attributes to the
repository; invalid form submissions follow Laravel's normal redirect-with-
errors behavior. The model stores the price columns as decimal(12,6) and
casts them to six-place decimal values.

## Alpha Vantage FX import

The Data Import form requires an existing pair, existing timeframe, and a
credential string no longer than 255 characters. The request removes the
credential from normal request input and exposes it only for validation and
the provider call; it is not saved to the import-session table, market-price
records, or redirect old input.

`AlphaVantageDataProvider` sends an HTTPS GET to
`https://www.alphavantage.co/query` with `outputsize=full`, base/quote symbols,
the credential, and provider function/interval derived from the timeframe
name. It supports these labels:

| Timeframe labels (case-insensitive after trim) | Provider mapping |
|---|---|
| `1d`, `daily`, `day` | `FX_DAILY` |
| `1w`, `weekly`, `week` | `FX_WEEKLY` |
| `1mo`, `monthly`, `month` | `FX_MONTHLY` |
| `1m`, `5m`, `15m`, `30m`, `60m` and corresponding `min` labels | FX intraday interval |
| Whole-hour labels such as `2h`, `4hr`, `6 hours` where the hour amount divides 24 and is 1–24 | Fetch 60-minute data, aggregate hourly candles |

Minute intervals other than 1, 5, 15, 30, or 60 fail validation.
Unsupported labels and hour amounts fail validation. Hourly aggregation groups
provider candles into day-local hour buckets, taking first open, maximum high,
minimum low, and last close for each bucket; it does not synthesize missing
hours or require complete buckets. Daily, weekly, and monthly provider dates
are converted to midnight `Y-m-d H:i:s`. Intraday times are normalized to
seconds when needed. Timestamps are sorted ascending and are not timezone
converted by this integration.

The connector requires an FX time-series payload with numeric general OHLC
values and expected date/time strings. It does not synthesize bid/ask values
or apply the push API's OHLC-consistency test. Connection failures, non-2xx
responses, provider quota/credential notices, empty time series, unsupported
timeframes, and malformed payloads are surfaced as request/validation or
runtime errors. The UI displays normal validation errors; malformed
unexpected response shapes may be reported as server errors and should be
diagnosed from Laravel logs.

## Authenticated incoming-candle API

The Workbench creates an import session for one existing pair and timeframe.
The token is generated as a random 64-character string; only its SHA-256 hash
is persisted. The clear token is flashed once to the browser session and
must be copied for the client. Only one active session is permitted for a
given pair/timeframe. Starting a duplicate selection is rejected. Stopping
the session changes its state to `stopped`, records stop time, and causes its
token to fail authorization. Historical stopped sessions remain stored and
continue to reference their pair/timeframe.

`POST /api/data-import/candles` is throttled at 120 requests per minute. A
valid bearer token must match an active session. Request validation requires
an array of 1–1,000 candles. Each candle requires:

| Field | Validation |
|---|---|
| `time` | Required, exact `Y-m-d H:i:s`, unique within the submitted array |
| `open`, `high`, `low`, `close` | Required numeric, between 0 and 999999.999999 |
| `bid_open/high/low/close`, `ask_open/high/low/close` | Individually nullable; when supplied numeric and between 0 and 999999.999999 |

After Laravel field validation, the action rejects general OHLC where high is
less than open, close, or low, or low is greater than open, close, or high.
For each bid/ask side, it performs the same check only when that side has all
four values. Partially supplied quote fields are not cross-validated. Zero is
accepted by the API even though positive prices are generally more suitable
for backtesting.

The endpoint associates every candle with the session's pair/timeframe and
returns `{"imported": N}`. An invalid/absent/stopped token returns HTTP 401;
field or OHLC validation errors return HTTP 422. Extra fields are not copied
into stored price columns. There is no explicit all-batch database transaction
in the receive action; if a storage failure occurs during the repository's
multiple update/insert operations, callers should inspect persisted records
before retrying.

## Duplicate keys, persistence, and data quality

Manual forms reject an already-existing pair/timeframe/timestamp combination
at request validation. Push requests reject duplicate `time` values within
one payload and upsert a previously stored timestamp for the session's
pair/timeframe. The external provider path uses the same repository importer
and upserts by pair/timeframe/date. Duplicate keys within an importer input
are collapsed before database operations, with the last value replacing
earlier values in that input.

The importer reports the number of distinct candle keys processed as
inserted-or-updated, not the number of rows newly created. The database
migrations do not enforce a unique constraint on
`(pair_id, timeframe_type_id, price_date)`; manual validation and the import
code are not a substitute for a database uniqueness invariant for direct
writers. There is also no explicit transaction around a complete provider or
push batch. Back up/inspect data before retrying after an infrastructure
failure.

All ingestion paths store naive timestamps as supplied or normalized; no
source timezone is attached. The timeframe record is only a label and the
push API does not validate interval spacing. The Alpha Vantage flow alone
maps known names to provider intervals and aggregates supported multi-hour
names.

## Verification and related documentation

The request, provider, receiver-token, repeated-timestamp, OHLC-validation,
credential-handling, and hourly aggregation behaviors are tested in
[`DataImportFeatureTest`](../../tests/Feature/DataImportFeatureTest.php).
Related: [Data Management](../user-guide/data-management.md),
[Market Data](../user-guide/market-data.md), and
[Market data management](market-data-management.md).
