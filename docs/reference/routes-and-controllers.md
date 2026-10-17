# Routes and controllers

This reference describes the application routes registered by Laravel for the
current source tree. The route names and methods were checked with
`php artisan route:list`. Framework/package routes can vary with installed
packages; use that command as the runtime inventory.
The main request and execution paths are diagrammed in
[Application flows](../architecture/application-flow.md).

## Middleware and access control

- Workbench endpoints use Laravel's `web` middleware group.
- API endpoints use Laravel's `api` middleware group. The pushed-candle
  endpoint adds `throttle:120,1`.
- The Backtest and ordinary Workbench FormRequests return `true` from
  `authorize()`; no authentication or policy middleware is registered for
  these routes. The pushed-candle request instead authorizes a bearer token
  against an active import session and returns JSON `401` if it does not match.
- Validation failures follow Laravel's request behavior: web form requests
  redirect back with validation errors; requests that expect JSON receive JSON
  validation errors with status `422`. API clients should send an appropriate
  `Accept: application/json` header.

## Workbench routes

All resource routes below use the `web` middleware group and return Blade views
for reads or redirects after writes. Form validation is performed by the
resource's FormRequest. For each resource, the standard registered route
pattern is:

| Operation | Method and path | Route name suffix |
|---|---|---|
| List | `GET /workbench/{resource}` | `index` |
| Create form | `GET /workbench/{resource}/create` | `create` |
| Create | `POST /workbench/{resource}` | `store` |
| Show | `GET /workbench/{resource}/{parameter}` | `show` |
| Edit form | `GET /workbench/{resource}/{parameter}/edit` | `edit` |
| Update | `PUT` or `PATCH /workbench/{resource}/{parameter}` | `update` |
| Delete | `DELETE /workbench/{resource}/{parameter}` | `destroy` |

The registered resource roots, route-name prefixes, parameters, controllers,
and request constraints are:

| Resource | Root and route-name prefix | Parameter | Controller / request |
|---|---|---|---|
| Market types | `/workbench/market-types`, `workbench.market-types.*` | `{marketType}` | `MarketTypeController`; `MarketTypeRequest` trims `name` and requires a unique string of at most 50 characters. |
| Market pairs | `/workbench/market-pairs`, `workbench.market-pairs.*` | `{marketPair}` | `MarketPairController`; `MarketPairRequest` normalizes base/quote to uppercase and requires both as strings of at most 10 characters, an existing market type, and a quantity unit in `units`, `contract`, or `lot`. Base/quote must be unique within that type. `contract_size` is required for `contract`/`lot` and must be positive with at most 8 decimal places. |
| Market prices | `/workbench/market-prices`, `workbench.market-prices.*` | `{marketPrice}` | `MarketPriceController`; `MarketPriceRequest` requires an existing pair/timeframe, `price_date` (`Y-m-d H:i:s`) unique for that pair/timeframe, and a positive `close` with at most 6 decimal places. `open`, `high`, `low`, `price_at_8`, and bid/ask OHLC are optional nullable positive prices with at most 6 decimal places. |
| Timeframes | `/workbench/timeframes`, `workbench.timeframes.*` | `{timeframe}` | `TimeframeTypeController`; `TimeframeTypeRequest` trims `name` and requires a unique string of at most 50 characters. |

Market-price listing accepts optional `pair_id` and `timeframe_type_id`
filters; both must identify existing records. Resource requests do not define
JSON resources or an API version: their successful responses are Workbench
pages and redirects.

The following non-resource Workbench routes are also registered:

| Method and path | Route name | Controller action | Request/response |
|---|---|---|---|
| `GET /workbench` | `workbench.index` | `DashboardController@index` | Renders the dashboard view. |
| `GET /workbench/market-exploration` | `workbench.market-exploration.index` | `MarketExplorationController@index` | Optional `pair_id`, `timeframe_type_id`, `from`, and `to` query filters. When supplied, identifiers must exist; dates use `Y-m-d`, and `to` must be on or after `from`. Renders a view. |
| `GET /workbench/data-import` | `workbench.data-import.index` | `DataImportController@index` | Renders the import/session page. |
| `POST /workbench/data-import/incoming-sessions` | `workbench.data-import.incoming-sessions.start` | `DataImportController@startIncoming` | Requires existing `pair_id` and `timeframe_type_id`; redirects back with the raw bearer token shown once. |
| `POST /workbench/data-import/incoming-sessions/{sessionId}/stop` | `workbench.data-import.incoming-sessions.stop` | `DataImportController@stopIncoming` | `sessionId` must be numeric; stops the active session and redirects back. |
| `POST /workbench/data-import/external-provider` | `workbench.data-import.external-provider` | `DataImportController@importExternalProvider` | Requires existing pair/timeframe IDs and `external_provider_credential` (string, max 255); the request keeps the credential separately while excluding it from the ordinary input bag. Redirects back with an import count. |
| `GET /workbench/tests/create` | `workbench.tests.create` | `BacktestController@createTest` | Renders the setup form. |
| `POST /workbench/tests` | `workbench.tests.run` | `BacktestController@runTest` | Validated by `RunWorkbenchBacktestRequest`; runs a single pair synchronously or creates queued market-type pair runs. Redirects to the corresponding result page. |
| `GET /workbench/tests/results` | `workbench.tests.results` | `BacktestController@results` | Reads the single-pair result from the session; redirects to the setup page with a status message if none is present. Accepts result-analysis query parameters. |
| `GET /workbench/tests/market-type-runs/{marketTypeBacktestRun}` | `workbench.tests.market-type-runs.show` | `BacktestController@marketTypeRun` | Renders the persisted run overview. |
| `GET /workbench/tests/market-type-runs/{marketTypeBacktestRun}/pairs/{pairRun}` | `workbench.tests.market-type-runs.pairs.show` | `BacktestController@marketTypePairResult` | Loads one persisted pair result and renders its analysis page; analysis query parameters use `BacktestResultsRequest`. |
| `GET /workbench/tests/market-type-runs/{marketTypeBacktestRun}/overall` | `workbench.tests.market-type-runs.overall` | `BacktestController@marketTypeOverallResult` | Combines persisted pair results and renders overall analysis; analysis query parameters use `BacktestResultsRequest`. |

Backtest form fields and detailed execution settings are described in
[Configuration](../getting-started/configuration.md) and
[Running backtests](../user-guide/running-backtests.md). Result filters,
including cluster, stress, pagination, and Monte Carlo options, are validated
by `BacktestResultsRequest`; their meanings are covered by
[Performance metrics](../features/performance-metrics.md),
[Stress testing](../features/stress-testing.md), and
[Monte Carlo](../features/monte-carlo.md).

## API routes

| Method and path | Middleware | Controller action | Input and response |
|---|---|---|---|
| `POST /api/engine/specific-pair-test` | `api` | `BacktestController@runSpecificPairTest` | `RunSpecificPairTestRequest` validates pair base/quote, date range, activity hours, excluded weekdays, and optional execution configuration. Returns a JSON resource collection of per-candle decisions plus order history, capital values, quantity metadata, and execution configuration. No authentication middleware is registered. |
| `POST /api/data-import/candles` | `api`, `throttle:120,1` | `DataImportApiController@receive` | Requires the bearer token of an active import session and a `candles` array of 1–1000 entries. Each entry requires distinct `time` (`Y-m-d H:i:s`) and OHLC numeric values from 0 through 999999.999999; bid/ask OHLC are optional nullable values in the same range. Returns `{"imported": count}`; no matching active token returns JSON `401`. |

The pushed-candle payload's price bounds and details are documented in
[Data ingestion and validation](../features/data-ingestion-and-validation.md).
The specific-pair request's complete validation rules are implemented by
[`RunSpecificPairTestRequest`](../../app/Feature/Backtest/Request/RunSpecificPairTestRequest.php).

## Framework-provided routes

The current route listing also includes:

- `GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS /` — framework redirect endpoint
  registered by `Route::redirect`, redirecting to `/workbench`.
- `GET|HEAD /up` — Laravel's configured health endpoint.
- `GET|HEAD /storage/{path}` and `PUT /storage/{path}` — named
  `storage.local` and `storage.local.upload`; these are framework filesystem
  routes, not feature controllers.

They are included here to distinguish the registered route inventory from
Nikaido's feature-owned controller routes.
