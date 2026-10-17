# Market Types and Timeframes

Market types group market pairs. Timeframes label candle series and are
selected together with a pair when entering data or running a backtest. They
do not define a market calendar or automatically resample candle intervals.
For market-price entry and imports see [Data Management](data-management.md).

## Create and manage a market type

Open **Market Types** (`/workbench/market-types`) and choose **Add market
type**. Enter a required name up to 50 characters. The name is trimmed and
must be unique. On success, the Workbench returns to the list with a
confirmation. The table is alphabetically ordered, paginated, and shows a
generated numeric ID (`MT-0001`-style display) and pair count. Open **View**
to inspect the ID and linked-pair count, or choose **Edit** to change the
name.

The resource routes are `GET /workbench/market-types` (list),
`GET /workbench/market-types/create` and `POST /workbench/market-types`
(create), `GET /workbench/market-types/{id}` (show),
`GET /workbench/market-types/{id}/edit` (edit),
`PUT`/`PATCH /workbench/market-types/{id}` (update), and
`DELETE /workbench/market-types/{id}` (delete). Create and update redirect
to the list with a success message. A delete failure also redirects to the
list, with a validation error explaining the pair dependency.

Editing changes the label associated with the same ID; it does not move pairs
or rewrite their market-price rows. Existing market-type runs copy the type
name and their results at run time, so editing the name does not rewrite
saved runs.

Delete from the list using **Delete** and confirm the browser prompt. A type
with any market pairs cannot be deleted. The request redirects to the list
with an error. Delete or reassign dependent pairs first; a pair that still
has prices or is referenced by an import session has its own deletion guard.

## Create and manage a timeframe

Open **Timeframes** (`/workbench/timeframes`) and choose **Add timeframe**.
Enter a required name up to 50 characters. It is trimmed and must be unique.
There is no separate interval-unit or duration field. Examples such as `15m`,
`4h`, and `1d` are labels; choose labels that accurately describe the data
you import.

The resource routes are `GET /workbench/timeframes` (list),
`GET /workbench/timeframes/create` and `POST /workbench/timeframes`
(create), `GET /workbench/timeframes/{id}` (show),
`GET /workbench/timeframes/{id}/edit` (edit),
`PUT`/`PATCH /workbench/timeframes/{id}` (update), and
`DELETE /workbench/timeframes/{id}` (delete). Create and update redirect to
the timeframe detail page with a success message. A delete failure redirects
to the list with the relationship error.

The alphabetic list shows a generated `TF-0001`-style ID and the number of
market-price records using each timeframe. **View** shows those details;
**Edit** changes the name while preserving the ID referenced from candles
and import sessions. Success returns to the timeframe detail page.

Delete from the list with **Delete** and confirm. Deletion is rejected while
any market-price row uses the timeframe or any import-session row references
it (including a stopped session). The error is shown on the list page. Remove
or replace those references before retrying; timeframe deletion does not
cascade to candle rows.

## How they relate to pairs, prices, and backtests

Each market pair has one `market_type_id`. Market prices reference one
`pair_id` and one `timeframe_type_id`; import sessions also pin one pair and
timeframe. A market-type backtest selects pairs belonging to that type which
have price records for the selected timeframe and requested date range.
Backtest code selects a timeframe by ID, not by parsing its name.

The Alpha Vantage connector is an exception to the otherwise free-form
timeframe labels: it maps a limited set of names to provider intervals. A
timeframe record can therefore be valid for manual data or push imports but
unsupported by the external connector. The connector's supported names and
aggregation are documented in [Data Management](data-management.md).

Market types are organizational metadata, not activation of special trading
rules for FX, crypto, equities, or futures. Market mechanics depend on the
pair quantity unit and the candle values supplied; see
[Market support](../features/market-support.md).

## Pairs as required dependencies

When creating a market pair, first choose an existing market type, then
provide base and quote symbols. Values are trimmed, uppercased, required, and
limited to 10 characters. The base/quote combination must be unique within
that market type; identical symbols can exist in a different market type.
Select `units`, `contract`, or `lot`. Contract and lot pairs require a positive
contract-size/multiplier value with up to eight decimal places. These
settings influence strategy sizing, risk, and P&L; changing them can alter
future backtests even though the historical candle rows are unchanged. See
[Capital and position sizing](capital-and-position-sizing.md).

Pairs can be edited, including changing their market type or symbols. Existing
price rows refer to the pair's ID, so changing symbols changes how those
records are labeled, not their price contents. Deleting a pair is blocked
while it has any price history or any import session reference, whether active
or stopped. Market type records themselves can only be deleted when no pairs
refer to them.

## Validation, errors, and further details

Invalid or duplicate names show field-level validation errors and preserve
the submitted form values. Attempting to delete a referenced record redirects
back to its list with a relationship error; records are not silently
cascade-deleted. A missing record ID produces the normal not-found response.

For the exact schema, constraints, and persistence behavior see
[Market types and timeframes](../features/market-types-and-timeframes.md).
For pair and candle CRUD see [Data Management](data-management.md).
