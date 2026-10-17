# Data Management

This guide covers the Workbench operations for adding, reviewing, editing, and
deleting market records, plus the supported import workflows. It does not
describe a CSV upload: the current application has no file-import UI or
command. For the candle fields and research-quality checks, see
[Market Data](market-data.md).

## Set up the records first

The dependency chain is:

1. Create a [market type](market-types-and-timeframes.md).
2. Create a market pair in that type.
3. Create a [timeframe](market-types-and-timeframes.md).
4. Add or import candles for the selected pair and timeframe.

Use the Workbench links **Market Types**, **Market Pairs**, **Timeframes**,
**Market Prices**, and **Data Import** from the navigation. A new market-price
form cannot be submitted until at least one pair and timeframe exist. Details
about market type and timeframe edits/deletion are in the dedicated
[guide](market-types-and-timeframes.md).

The market-price resource routes are under `/workbench/market-prices`:

| Operation | Route |
|---|---|
| Browse | `GET /workbench/market-prices` |
| New form / create | `GET /workbench/market-prices/create`, then `POST /workbench/market-prices` |
| Inspect / edit | `GET /workbench/market-prices/{id}`, `GET /workbench/market-prices/{id}/edit` |
| Update / delete | `PUT` or `PATCH /workbench/market-prices/{id}`, `DELETE /workbench/market-prices/{id}` |

The Workbench posts standard HTML forms to these routes; they are not a
separate JSON CRUD API.

## Enter a candle manually

Open **Market Prices** (`/workbench/market-prices`) and select **Add market
price**. Choose the market pair and timeframe, enter the candle date/time,
and provide at least a general `close`. General `open`, `high`, and `low` are
optional in the form, as are all bid and ask fields and the legacy `price_at_8`.
The model will store the submitted values; it does not infer missing values.

The form accepts a timestamp displayed as `Y-m-d H:i` and normalizes it to
seconds, or `Y-m-d H:i:s`. Every supplied price must be numeric, greater than
zero, and have no more than six decimal places. Pair and timeframe IDs must
refer to existing records. The tuple `(pair, timeframe, timestamp)` must be
unique according to request validation. The form does not check general or
bid/ask OHLC high/low consistency; review values yourself before saving.

On success, the browser opens the created candle's detail page with a success
message. Validation errors redirect back to the form and display field errors.
No price is added when request validation fails.

## View, filter, and inspect records

The Market Prices table displays total matching records, timestamp, pair,
timeframe, general OHLC, and bid/ask close. Filter by pair, timeframe, both,
or neither; each filter must be a valid existing ID. Results are paginated at
25 rows per page, newest timestamp first with row ID as a tie-break, and
pagination preserves the filters.

Open a timestamp or choose **View** to inspect the record ID, general OHLC,
`price_at_8`, and bid/ask OHLC values. Choose **Edit** from the list or detail
view to load the same form prefilled with current values.

## Edit or delete a candle

The edit form uses the same required fields and validation as create. You may
change pair, timeframe, timestamp, and prices. The uniqueness check excludes
the current record, but rejects a timestamp already assigned to another
record for that pair and timeframe. A successful update redirects to the
record detail page. An invalid update redirects back with errors and leaves
the prior record unchanged.

Choose **Delete** from the list and confirm the browser prompt. The
`DELETE /workbench/market-prices/{marketPrice}` request deletes that one
record and redirects to the list with a success message. There is no
application-level dependency guard for a price row. Deleting or editing a
candle changes the input available to future backtests; it does not
automatically recalculate previously saved market-type results or reports.
Single-pair results are held in the browser session, while market-type runs
persist copied results; changing candles does not rewrite those copied
results.

## Import provider history

Open **Data Import** (`/workbench/data-import`), choose a pair and timeframe,
enter the provider credential, then choose **Pull provider data**. The form
posts to `/workbench/data-import/external-provider`. The credential is
required, sent to the provider for that request, and removed from the request
input so it is not retained in session old input or persisted with market
records.

The only built-in external connector is Alpha Vantage FX. It requests the
provider's available history for the selected pair and timeframe; it does not
allow a user-selected date range. The connector supports timeframe labels
recognized by its mapping: daily (`1d`, `daily`, `day`), weekly (`1w`,
`weekly`, `week`), monthly (`1mo`, `monthly`, `month`), minute values 1, 5,
15, 30, or 60 minutes, and whole-hour values from 1 through 24 that divide
24. Other labels fail validation. Hourly provider candles are grouped into
the requested number of hours; daily/weekly/monthly data is imported as
returned. Alpha Vantage supplies general OHLC only, so bid/ask fields remain
null.

The provider payload must contain numeric OHLC fields and valid date/time
strings in the expected provider format. Network connection errors, HTTP
errors, provider `Error Message`/`Information`/`Note`, unsupported intervals,
empty responses, and malformed candles are reported as errors and no
successful completion message is shown. Provider limits, credential validity,
and historical coverage are external dependencies. Successful import
redirects to Data Import and reports the number of candles inserted or
updated.

## Receive candle batches through the push API

On Data Import, select one pair and timeframe and choose **Start receiving**.
The application creates an active import session and displays a bearer token
once. Copy it immediately. A second active receiver session for the same pair
and timeframe is rejected. Stop the session from the Incoming sessions table
after sending; a stopped token can no longer be used. The page shows up to 10
recent sessions and all active sessions.

The receiver controls use `POST /workbench/data-import/incoming-sessions` to
start a session and
`POST /workbench/data-import/incoming-sessions/{sessionId}/stop` to stop it.
The incoming candle endpoint itself is separate from these browser forms.

Send `POST /api/data-import/candles` with these headers and a body such as:

```http
Authorization: Bearer <session-token>
Content-Type: application/json
```

```json
{
  "candles": [
    {
      "time": "2026-10-12 10:00:00",
      "open": 1.1025,
      "high": 1.1050,
      "low": 1.1010,
      "close": 1.1040,
      "bid_open": 1.1024,
      "ask_open": 1.1026
    }
  ]
}
```

Each request must include 1–1,000 candles. `time` is required, formatted
exactly `Y-m-d H:i:s`, and must be unique within that request. General OHLC
fields are all required and accept numeric values from 0 through
999999.999999. Bid/ask OHLC fields are individually optional and, when
provided, have the same numeric range. The receiver also rejects inconsistent
general OHLC (high below open/close/low or low above open/close/high). For a
bid or ask set it checks consistency only when all four OHLC values on that
side are non-null.

The API returns JSON `{"imported": N}` on success, where `N` counts candles
inserted or refreshed. The token is looked up by its SHA-256 hash and must
belong to an active session; an absent, unknown, or stopped token returns
401. Validation errors return 422 and identify invalid candle fields. The
endpoint is throttled to 120 requests per minute. The session fixes the pair
and timeframe, so clients cannot use its token to import into another
selection.

## Duplicate handling and consistency checks

Push submissions cannot repeat a timestamp within the same batch due to the
`distinct` validation. If a later request sends the same timestamp for the
same pair and timeframe, import updates that stored candle rather than adding
a second row. The external-provider import uses the same insert-or-update
repository path. When repeated keys occur inside a prepared import array, the
last value wins during in-memory keying.

The manual form rejects duplicate pair/timeframe/timestamp keys at validation
time. The database schema does not declare a composite unique constraint for
those fields, so other direct database writers are not protected by a
database-level uniqueness guarantee. Review existing records in Market Prices
before relying on uniqueness. Manual entry and provider ingestion do not
apply the push API's complete OHLC-consistency checks.

None of the supported ingestion paths establishes that a feed is complete,
correctly adjusted, or suitable for a trading simulation. See
[Market Data](market-data.md) for time conventions, bid/ask use, and
pre-backtest validation.

## Programmatic operations and error diagnosis

The supported programmatic candle ingestion is the authenticated push
endpoint above. There is no public CRUD JSON API for market prices and no
bulk CSV/Excel import feature. Application developers can use the existing
`MarketPrice` model/repository in application code, but direct writes bypass
some request-level validation and should not be treated as a supported
end-user import workflow.

For a failed operation:

- Check Laravel validation messages on the returned Workbench form.
- For provider imports, verify the credential, connection, provider quota,
  selected pair/timeframe, and the exact timeframe-label mapping.
- For a 401 push response, check that the bearer token is copied exactly and
  its receiver session is still active.
- For a 422 push response, check timestamp format, batch size, numeric bounds,
  and high/low consistency.
- For missing records in a backtest, filter Market Prices by the exact pair
  and timeframe and compare the available timestamps with the requested
  period. Backtests do not synthesize gaps.

Useful checks are covered by
[DataImportFeatureTest](../../tests/Feature/DataImportFeatureTest.php) and
[Running tests](../getting-started/running-tests.md). The technical import
contract is in [Data ingestion and validation](../features/data-ingestion-and-validation.md);
record persistence and relationships are in
[Market data management](../features/market-data-management.md).
