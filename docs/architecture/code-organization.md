# Code organization

Application code is organized first by capability under
[`app/Feature/`](../../app/Feature/), then by the responsibilities actually
used inside each feature. Shared infrastructure belongs to
[`app/Platform/`](../../app/Platform/). This is the structure present in the
autoloaded PHP namespaces and routes; older documentation examples using
`app/feature/` or `Engine`/`Workbench` as application namespaces are not the
current source layout.

In particular, [`coding-standar.md`](../coding-standar.md) still uses the
lowercase `app/feature/` spelling and illustrative `Engine`/`Workbench`
directories in its examples. The live application path is case-sensitive and
uses `app/Feature/`; the test tree retains some of those older group names.

## Feature responsibilities

| Feature | Responsibility evidenced by its routes and classes |
|---|---|
| [`Dashboard`](../../app/Feature/Dashboard/) | Workbench landing route and Blade response. |
| [`MarketTypes`](../../app/Feature/MarketTypes/) | Market-type resource controller, validation, CRUD Actions, Eloquent model, and repository. |
| [`MarketPairs`](../../app/Feature/MarketPairs/) | Pair resource and typed pair definition/quantity data; pair persistence and selection of pair-linked candle history. |
| [`Timeframes`](../../app/Feature/Timeframes/) | Timeframe resource, validation, CRUD Actions, model, and repository. |
| [`MarketPrice`](../../app/Feature/MarketPrice/) | Candle CRUD, listing/filtering, model relations, and candle queries/import persistence. |
| [`DataImport`](../../app/Feature/DataImport/) | Provider ingestion, incoming-session lifecycle, push API, and request validation. |
| [`MarketExploration`](../../app/Feature/MarketExploration/) | Selection and presentation-data preparation for market candles. |
| [`Backtest`](../../app/Feature/Backtest/) | Workbench/API backtest entry points, input/configuration, simulation and analysis, multi-pair jobs, and saved market-type run records. |

## Component roles as implemented

These are responsibilities visible in the current code, not a requirement
that every operation must pass through every type of component.

- **Routes** are feature-owned files required from
  [`routes/web.php`](../../routes/web.php), or `api.php` files registered
  through [`bootstrap/app.php`](../../bootstrap/app.php). They map an HTTP
  method and path to a controller method.
- **Controllers** are HTTP boundary classes. They resolve validated input,
  call Actions, and return Blade views, redirects, or JSON resources. For
  example, MarketPriceController delegates CRUD operations, while
  BacktestController also selects a run branch, saves a single-pair result
  in the session, and maps analysis results to the page/API representation.
  DashboardController directly renders its landing page.
- **FormRequests** define request authorization and input rules. Some also
  normalize HTTP values or create typed Backtest configuration objects, as in
  [`RunWorkbenchBacktestRequest`](../../app/Feature/Backtest/Request/RunWorkbenchBacktestRequest.php).
  This is transport validation; domain/configuration classes can enforce
  additional invariants.
- **Actions** are operation entry points. Simple CRUD Actions often delegate
  directly to their feature Repository. Larger Actions coordinate
  repositories, Processes, jobs, validation failures, or other feature
  entry points. For example,
  [`LoadBacktestSetupPageAction`](../../app/Feature/Backtest/Action/LoadBacktestSetupPageAction.php)
  assembles lists through MarketPairs, MarketTypes, and Timeframes Actions;
  [`RunSpecificPairBacktestAction`](../../app/Feature/Backtest/Action/RunSpecificPairBacktestAction.php)
  selects data and calls the computation Process.
- **Repositories** contain Eloquent reads and persistence operations. They
  load relationships, filter/order records, map storage results, and expose
  operations such as pair candle selection or batched candle import. For
  example, [`MarketPriceRepository`](../../app/Feature/MarketPrice/Repository/MarketPriceRepository.php)
  handles price CRUD, filtered pagination, candle loading, and import writes.
- **Models** are Eloquent persistence representations with fillable fields,
  casts, and relations. MarketType, MarketPair, TimeframeType, and
  MarketPrice define the stored market-data graph. Backtest run models
  represent saved market-type run and pair-run state.
- **Data and Config classes** represent structured values passed between
  operations or hold validated simulation settings. They are used
  extensively in Backtest and in pair definition/market-data code; they are
  not universal wrappers around every input.
- **Processes and Rules** occur primarily in Backtest. Processes contain
  calculations and transformations such as pair simulation, Monte Carlo,
  stress testing, and portfolio capital synchronization. The current Rules
  area includes trading-schedule policy. The principal
  [`SpecificPairBacktestProcess`](../../app/Feature/Backtest/Process/SpecificPairBacktestProcess.php)
  receives prepared candle input rather than performing an HTTP request or
  repository query.
- **Jobs** are Laravel queue jobs. The implemented market-type pair job
  calls an Action and records success/failure for one persisted pair run.
- **Execution helpers** under `Backtest/Execution/` implement execution
  mechanics such as fixed slippage and partial fills, called by the
  simulation process.
- **Platform Strategy classes** provide the strategy execution boundary.
  [`Strategy`](../../app/Platform/Strategy/Strategy.php) delegates to the
  current moving-average decision generator. Strategy decisions and execution
  context are passed to the Backtest process.

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
the MarketPrice repository to persist candles. Controller methods do not
coordinate these feature integrations through direct cross-feature
repository calls in the inspected paths.

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
is the most decomposed feature, with dedicated Config, Data, Execution, Job,
Process, Repository, Request, Resource, and Rules areas. Simpler features
have fewer component types. This is an observation of current organization,
not a claim that every class is small or that every feature uses all layers.

Some existing PHPUnit namespaces and directories retain names such as
`Tests\Unit\Engine`, `Tests\Unit\Workbench`, or `Tests\Unit\Strategy`, while
application classes are now organized under `App\Feature\...` and
`App\Platform\...`. Tests are organized by suite and subject rather than
mirroring each current feature's exact directory structure; see
[testing architecture](testing-architecture.md).
