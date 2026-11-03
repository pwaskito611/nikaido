# Code organization

Laravel-facing application code is organized by capability under
[`laravel/app/Feature/`](../../laravel/app/Feature/). Its Laravel-specific
platform adapters live in `laravel/app/Platform/`. Strategy implementation and
strategy types live under [`src/`](../../src/), with
[`src/main.php`](../../src/main.php) defining the current strategy entry
function. This is the structure present in the autoloaded PHP namespaces and
routes; older examples using `app/feature/` or `Engine`/`Workbench` namespaces
are not the current source layout.

Laravel's bootstrap, configuration, routes, database resources, public entry
point, views, runtime storage, and PHPUnit suites live under `laravel/`.
Laravel's public and Artisan entry points load
`laravel/bootstrap/app.php`; root `artisan` delegates administrative
commands to `laravel/artisan`. The root Composer project maps `App\` to
`laravel/app/` and `Nikaido\` to `src/`; the root `.env` is loaded regardless
of working directory.

## Feature responsibilities

| Feature | Responsibility evidenced by its routes and classes |
|---|---|
| [`Dashboard`](../../laravel/app/Feature/Dashboard/) | Workbench landing route and Blade response. |
| [`MarketTypes`](../../laravel/app/Feature/MarketTypes/) | Market-type resource controller, validation, CRUD Actions, Eloquent model, and repository. |
| [`MarketPairs`](../../laravel/app/Feature/MarketPairs/) | Pair resource and typed pair definition/quantity data; pair persistence and selection of pair-linked candle history. |
| [`Timeframes`](../../laravel/app/Feature/Timeframes/) | Timeframe resource, validation, CRUD Actions, model, and repository. |
| [`MarketPrice`](../../laravel/app/Feature/MarketPrice/) | Candle CRUD, listing/filtering, model relations, and candle queries/import persistence. |
| [`DataImport`](../../laravel/app/Feature/DataImport/) | Provider ingestion, incoming-session lifecycle, push API, and request validation. |
| [`MarketExploration`](../../laravel/app/Feature/MarketExploration/) | Selection and presentation-data preparation for market candles. |
| [`Backtest`](../../laravel/app/Feature/Backtest/) | Workbench/API backtest entry points, input/configuration, simulation and execution, multi-pair jobs, and saved market-type run records. |
| [`Evaluation`](../../laravel/app/Feature/Evaluation/) | Workbench backtest-result pages, performance metrics, clustering, Monte Carlo simulation, and fluctuation stress testing. |

## Component roles as implemented

These are responsibilities visible in the current code, not a requirement
that every operation must pass through every type of component.

- **Routes** are feature-owned files required from
  [`routes/web.php`](../../laravel/routes/web.php), or `api.php` files registered
  through [`bootstrap/app.php`](../../laravel/bootstrap/app.php). They map an HTTP
  method and path to a controller method.
- **Controllers** are HTTP boundary classes. They resolve validated input,
  call Actions, and return Blade views, redirects, or JSON resources. For
  example, MarketPriceController delegates CRUD operations, while
  BacktestController runs tests and saves single-pair results in the session.
  EvaluationController handles result pages and delegates analysis to
  Evaluation Actions. DashboardController directly renders its landing page.
- **FormRequests** define request authorization and input rules. Some also
  normalize HTTP values or create typed Backtest configuration objects, as in
  [`RunWorkbenchBacktestRequest`](../../laravel/app/Feature/Backtest/Request/RunWorkbenchBacktestRequest.php).
  This is transport validation; domain/configuration classes can enforce
  additional invariants.
- **Actions** are operation entry points. Simple CRUD Actions often delegate
  directly to their feature Repository. Larger Actions coordinate
  repositories, Processes, jobs, validation failures, or other feature
  entry points. For example,
  [`LoadBacktestSetupPageAction`](../../laravel/app/Feature/Backtest/Action/LoadBacktestSetupPageAction.php)
  assembles lists through MarketPairs, MarketTypes, and Timeframes Actions;
  [`RunSpecificPairBacktestAction`](../../laravel/app/Feature/Backtest/Action/RunSpecificPairBacktestAction.php)
  selects data and calls the computation Process.
- **Repositories** contain Eloquent reads and persistence operations. They
  load relationships, filter/order records, map storage results, and expose
  operations such as pair candle selection or batched candle import. For
  example, [`MarketPriceRepository`](../../laravel/app/Feature/MarketPrice/Repository/MarketPriceRepository.php)
  handles price CRUD, filtered pagination, candle loading, and import writes.
- **Models** are Eloquent persistence representations with fillable fields,
  casts, and relations. MarketType, MarketPair, TimeframeType, and
  MarketPrice define the stored market-data graph. Backtest run models
  represent saved market-type run and pair-run state.
- **Data and Config classes** represent structured values passed between
  operations or hold validated simulation settings. They are used
  extensively in Backtest and Evaluation and in pair definition/market-data
  code; they are not universal wrappers around every input.
- **Processes and Rules** occur primarily in Backtest and Evaluation.
  Backtest Processes contain pair simulation and portfolio capital
  synchronization; Evaluation Processes contain cluster analysis, Monte
  Carlo, and stress testing. The Backtest Rules area includes trading-schedule
  policy. The principal
  [`SpecificPairBacktestProcess`](../../laravel/app/Feature/Backtest/Process/SpecificPairBacktestProcess.php)
  receives prepared candle input rather than performing an HTTP request or
  repository query.
- **Jobs** are Laravel queue jobs. The implemented market-type pair job
  calls an Action and records success/failure for one persisted pair run.
- **Execution helpers** under `Backtest/Execution/` implement execution
  mechanics such as fixed slippage and partial fills, called by the
  simulation process.
- **Strategy bridge**: [`Strategy`](../../laravel/app/Platform/Strategy/Strategy.php)
  is the Laravel adapter used by the Backtest process. It delegates decisions
  to `Nikaido\Strategy\main()` in [`src/main.php`](../../src/main.php).
  Strategy decisions, signals, and execution context are owned by `src/`.
- **External market-data provider integration** is represented by
  [`ExternalMarketDataProvider`](../../laravel/app/Feature/DataImport/Contract/ExternalMarketDataProvider.php).
  The DataImport Action consumes this contract, while
  [`AppServiceProvider`](../../laravel/app/Platform/Infrastructure/Providers/AppServiceProvider.php)
  currently binds it to Alpha Vantage. Replacing the provider means
  implementing the same normalized candle contract and changing that binding;
  the current Workbench does not offer provider selection. For owned datasets
  or a different input format, extend the DataImport ingestion path rather
  than changing backtest candle processing. Details are in
  [Data ingestion and validation](../features/data-ingestion-and-validation.md#using-another-provider-or-your-own-market-data).

No global Service, UseCase, or generic Repository hierarchy is present in
these application paths. Component usage varies with the work: the dashboard
uses no Action or repository; a simple CRUD Action may only call one
repository; a market-type backtest Action spans selection, persistence, and
queue dispatch.

## Communication and data flow

HTTP input enters through a feature route and controller. When a FormRequest
is used, validated values are passed into an Action or converted into a typed
input/configuration. Actions pass values between feature repositories and
computation components. Repositories use Eloquent models to read or write
database records; results return through the Action to the controller for
view, redirect, session, or API response handling.

Cross-feature collaboration is visible in Action-level orchestration:
Backtest setup uses market-pair/type/timeframe listing Actions, and the
Backtest execution path uses pair/timeframe repositories. The DataImport
feature uses market-pair/timeframe repositories to resolve import targets and
the MarketPrice repository to persist candles. Evaluation Actions load
Backtest results and coordinate them with Evaluation analysis. Controllers do
not coordinate these feature integrations through direct cross-feature
Actions or repositories.

Validation is distributed by boundary and purpose:

1. FormRequests validate and normalize submitted HTTP values.
2. Configuration and data constructors perform checks required by the
   corresponding typed operation.
3. Actions reject operation-specific conditions, such as absent candles or
   synchronous queue configuration.
4. Processes enforce simulation invariants and may raise domain/runtime
   exceptions.
5. Repositories and database constraints provide persistence-level
   behavior. Not all logical relationships have matching database foreign
   keys; see the [overview](overview.md#persistence-and-feature-relationships).

## Separation and observed limits

The code separates feature ownership and commonly keeps transport,
orchestration, persistence, and computation in different classes. Backtest
and Evaluation own separate execution and result-analysis responsibilities;
each uses only the component areas needed for its work. Simpler features have
fewer component types. This is an observation of current organization, not a
claim that every class is small or that every feature uses all layers.

Some existing PHPUnit namespaces and directories retain names such as
`Tests\Unit\Engine`, `Tests\Unit\Workbench`, or `Tests\Unit\Strategy`, while
Laravel application classes are organized under `App\Feature\...` and
`App\Platform\...`, and strategy types under `Nikaido\Strategy\...`. Tests are
organized by suite and subject rather than mirroring each current feature's
exact directory structure; see
[testing architecture](testing-architecture.md).
