# Data ingestion and validation

## Supported ingestion paths

Nikaido stores candles from two paths:

1. Manual create/update forms under `/workbench/market-prices`.
2. The authenticated incoming-candle API at `POST /api/data-import/candles`.
   Enable it by starting a pair/timeframe session in the Data Import page.

The Data Import page does not fetch or poll external providers. There is no
CSV/Excel uploader, scheduled sync, public market-price CRUD API, or
general-purpose file import command. Supported user workflows are described
in [Data Management](../user-guide/data-management.md).

Fundamental observations use a separate authenticated push endpoint described
below; they are not candle records and do not use a pair/timeframe import
session.

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
consistency, bid below ask, or completeness of any bid/ask side. This differs
from pushed-candle validation.

## Authenticated incoming-candle API

The Workbench creates an import session for one existing pair and timeframe.
The token is a random 64-character string; only its SHA-256 hash is persisted.
The clear token is shown once and must be copied for the sending client. Only
one active session is permitted for a given pair/timeframe. Starting a
duplicate selection is rejected. Stopping a session invalidates its token;
historical stopped sessions remain stored and reference their pair/timeframe.

`POST /api/data-import/candles` is throttled at 120 requests per minute. A
valid bearer token must match an active session. Request validation requires
an array of 1–1,000 candles. Each candle requires:

| Field | Validation |
|---|---|
| `time` | Required, exact `Y-m-d H:i:s`, unique within the submitted array |
| `open`, `high`, `low`, `close` | Required numeric, between 0 and 999999.999999 |
| `bid_open/high/low/close`, `ask_open/high/low/close` | Individually nullable; when supplied numeric and between 0 and 999999.999999 |
| `volume` | Optional, non-negative numeric with at most eight decimal places |
| `volume_type` | Required when volume is provided; `traded` or `tick` |

After Laravel field validation, the action rejects general OHLC where high is
less than open, close, or low, or low is greater than open, close, or high.
For each bid/ask side, it performs the same check only when that side has all
four values. Partially supplied quote fields are not cross-validated. Zero is
accepted by the API even though positive prices are generally more suitable
for backtesting.

The sender must normalize provider-specific payloads and timeframes before
posting. The receiver associates every candle with the session's pair and
timeframe; it does not fetch data, infer timeframe spacing, or transform
source formats. The endpoint returns `{"imported": N}`. An invalid, absent,
or stopped token returns HTTP 401; field or OHLC validation errors return
HTTP 422. Extra fields are not copied into stored price columns.

There is no explicit all-batch database transaction in the receive action.
If a storage failure occurs during the repository's multiple update/insert
operations, callers should inspect persisted records before retrying.

## Authenticated incoming-fundamental API

The Data Import page offers a separate Fundamental observation receiver.
Select one Fundamental entity there to start its incoming import session. The
generated 64-character bearer token is stored only as a SHA-256 hash and
displayed once after the session starts. An entity cannot have another active
session until the current one is stopped. Stopping a session invalidates its
token; deleting the entity cascades to its sessions.

`POST /api/data-import/fundamentals/observations` accepts batches of 1–1,000
observations and is throttled at 120 requests per minute. The entity is
resolved from the active bearer-token session, not from a submitted
`fundamental_id`:

```json
{
  "observations": [
    {
      "code": "CPI",
      "name": "Consumer Price Index",
      "datetime": "2024-09-01 00:00:00",
      "released_at": "2024-10-10 08:30:00",
      "value": 1.1
    },
    {
      "code": "GDP",
      "name": "GDP growth",
      "datetime": "2024-07-01 00:00:00",
      "released_at": null,
      "value": {"growth": 2.4, "unit": "percent"}
    }
  ]
}
```

`code` is normalized to uppercase and must contain letters, digits, or
underscores, beginning with a letter. `datetime` and non-null `released_at`
must use exact `Y-m-d H:i:s` timestamps. `value` must be present and can be
any JSON value, including a scalar, object, array, or `null`. `released_at`
may be omitted or null; such an observation is stored but remains unavailable
to strategies. Duplicate `(code, datetime)` keys in one batch are rejected.

Retries are idempotent on `(fundamental_id, code, datetime)`: existing
observations are updated, including their name, value, and release time. The
entire batch is stored transactionally. A successful response reports
`imported`, `inserted`, and `updated` counts. A missing, invalid, or stopped
token returns HTTP 401; validation errors return HTTP 422. Since the current
schema has one row per indicator and observation timestamp, an update
overwrites the prior value rather than preserving a revision/vintage. Do not
use this upsert behavior for revised source data when historical vintages are
required to avoid look-ahead bias.

## Duplicate keys, persistence, and data quality

Manual forms reject an existing pair/timeframe/timestamp combination at
request validation. Push requests reject duplicate `time` values within one
payload and upsert a previously stored timestamp for the session's
pair/timeframe. The importer reports the number of distinct candle keys
processed as inserted-or-updated, not the number of rows newly created.

The database migrations do not enforce a unique constraint on
`(pair_id, timeframe_type_id, price_date)`; manual validation and import code
are not a substitute for a database uniqueness invariant for direct writers.
There is no explicit transaction around a complete push batch. Back up or
inspect data before retrying after an infrastructure failure.

All ingestion paths store naive timestamps as supplied; no source timezone is
attached. The timeframe record is only a label and the push API does not
validate interval spacing. Manual entry remains available for direct
correction or small data sets.

## Verification and related documentation

Receiver-token, repeated-timestamp, volume, and OHLC-validation behaviors are
tested in
[`DataImportFeatureTest`](../../laravel/tests/Feature/DataImportFeatureTest.php).
Related: [Data Management](../user-guide/data-management.md),
[Market Data](../user-guide/market-data.md), and
[Market data management](market-data-management.md).
