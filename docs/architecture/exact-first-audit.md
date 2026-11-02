# Exact-first application audit

This audit records the Exact-first changes made to application collection
processing. It is not a claim that every PHP statement or every business
algorithm was rewritten: imperative state machines, persistence adapters, and
framework boundaries remain where their existing semantics fit better.

## Scope and method

- Inventoried 174 PHP source files under `app/Feature/` and `app/Platform/`.
- Searched the application for Eloquent/Laravel collection contracts,
  repository `get()` calls, collection transforms, array transforms, and
  explicit iteration.
- Inspected the Exact `EXACT.MD`, installed source, and Query usage reference
  from Exact v2.2.0 before using its collection and query APIs.
- Followed the multiple-record repository results to their application
  consumers and view boundaries.
- Existing dependency-manifest changes were present in the worktree and were
  left untouched.

## Findings and changes

| Module / classes | Existing approach | Recommended approach | Action / classification | Reason |
|---|---|---|---|---|
| MarketPairs: `MarketPairRepository`, `ListAllMarketPairsAction` | `all()` returned an Eloquent Collection; warmup candles were reversed/concatenated with Laravel Collection operations. | Return `Vec` for loaded records and candles; return `LazySeq` for chunked eligible-pair iteration. | Changed — `REFACTOR` | Application consumers need ordered model sequences, not Eloquent collection methods. Database selection and chunking remain Eloquent. |
| MarketTypes: `MarketTypeRepository`, `ListAllMarketTypesAction` | `all()` exposed Eloquent Collection. | Return `Vec<MarketType>`. | Changed — `REFACTOR` | The records leave the repository for form/setup processing. |
| Timeframes: `TimeframeTypeRepository`, `ListAllTimeframeTypesAction` | `all()` exposed Eloquent Collection. | Return `Vec<TimeframeType>`. | Changed — `REFACTOR` | The records leave the repository for form/setup processing. |
| DataImport: `DataImportSessionRepository`, `LoadDataImportPageAction` | Active/recent sessions and their related selection lists were Eloquent Collections. | Return and carry `Vec` values through the Action to Blade. | Changed — `REFACTOR` | The view only iterates/counts the data; no Eloquent collection-specific operation is needed. Provider/API payloads remain arrays. |
| MarketPrice: `MarketPriceRepository`, `LoadMarketPriceFormPageAction`, `LoadMarketPriceListPageAction` | Candle selection already converted to `Vec`; pair/timeframe selection lists exposed Eloquent Collections. | Keep candle and selection lists as `Vec`; preserve Laravel pagination for the market-price table. | Changed — `REFACTOR` / `KEEP` | One conversion at the repository boundary avoids collection conversion in consumers. Pagination metadata and links are framework contracts. |
| MarketExploration: `LoadMarketExplorationPageAction`, `LoadMarketExplorationCandlesAction` | Selection lists were Laravel Collections; Blade used `firstWhere`; chart payload was an array. | Carry `Vec` lists and resolve selected models with Exact filtering; keep chart JSON as an array at the view boundary. | Changed — `REFACTOR` / `KEEP` | Selection is application processing; JSON serialization remains a boundary representation. |
| Backtest: `MarketTypeBacktestRunRepository`, `LoadMarketTypeOverallResultAction`, `InitiateMarketTypeBacktestAction` | Successful runs returned Eloquent Collection and queue IDs/pair streams used Laravel LazyCollection. | Return `Vec<MarketTypeBacktestPairRun>` and `LazySeq` after repository access. | Changed — `REFACTOR` | Downstream aggregation and dispatch now consume Exact sequences; SQL chunking and dispatch side effects remain Laravel-owned. |
| Backtest: `CombineMarketTypeBacktestResultsAction` | Nested loops assembled result/order arrays, `usort` ordered them, and a loop built managed portfolio orders. | Use Exact `Query` for flattening/projection/stable key ordering and `Vec` for capital totals and indexed model creation. | Changed — `REFACTOR` | This is in-memory collection composition after records have left persistence. Output arrays remain at the persisted result boundary. |
| Evaluation: `PrepareBacktestEvaluationPageAction`, results Blade | Blade used Laravel `collect()` for counts, cost aggregation, and labels. | Compute the values before rendering using `Vec`, `Map`, and Exact `Query::groupFold()`. | Changed — `REFACTOR` | Moves result processing out of presentation and removes Laravel Collection as the application data-processing layer. |
| Evaluation: `BacktestClusterAnalysisProcess` | Loops selected orders/fills into capital movements, manually sorted them, and iterated chart points; order matching looped through exit fills. | Query min/max and filtering for cluster bounds, flatten/map/sort for movements, and project chart coordinates with Query; keep sequential net capital as ordered collection aggregation. | Changed — `REFACTOR` | Separates collection selection/projection/order from the order-dependent capital sequence. |
| Evaluation: `FluctuationStressTestProcess` | A loop generated scenario orders and accumulated capital; repeated collection filters/folds calculated summary metrics and a long sequence appended metric objects. | Use Query `fold` for scenario capital and drawdown state, `sum` and `countBy` for metrics, and map metric definitions into Exact data. | Changed — `REFACTOR` | State transitions remain explicitly ordered reducers while in-memory selection, grouping, and output construction use Exact. |
| Evaluation: `MonteCarloSimulationProcess` | Loop-based value ordering and repeated full-list filters built histogram bins. | Query stable value sorting and one-pass `countBy` histogram bucketing. | Changed — `REFACTOR` | These are pure collection transformations; random sampling and equity simulation remain sequential. |
| DataImport: `AlphaVantageDataProvider` | Laravel `collect()` searched response keys; `usort` and a mutable bucket loop ordered/aggregated hourly candles. | Exact Query searches the response keys, sorts decoded candles, groups hourly values with `groupFold`, then sorts the serialized output. | Changed — `REFACTOR` / `KEEP` | Parsing/validation remains explicit at the external payload boundary; collection lookup and aggregation use Exact; provider output remains an array. |
| Backtest: `CombineMarketTypeBacktestResultsAction` cost-component normalization | A loop filtered keyed cost-configuration entries and capital totals used manual folds. | Use Exact `Map`/`Query` to filter keyed config and Exact Query sums/averages. | Changed — `REFACTOR` | The operation is pure in-memory collection processing; the configuration and result output shapes remain arrays. |
| Dashboard | Direct static view rendering; no application collection pipeline. | Keep as-is. | `KEEP` | No collection processing to replace. |
| DataImport provider, HTTP/FormRequests, repository writes, Eloquent models, and serialized results | Arrays and loops decode external provider input, validate fixed request fields, form database writes, and represent persisted/JSON structures. | Keep arrays at those boundaries; use Exact after data becomes an internal collection where useful. | `KEEP` / `EXCEPTION` | These arrays express external/framework/storage shapes or drive side-effectful batch writes; replacing them would add conversions without clarifying contracts. |
| Backtest and Evaluation simulation/process classes; Strategy | Explicit loops remain in stochastic sampling/equity simulation, strategy execution, portfolio synchronization, and stateful data ingestion. | Use Query/Exact collections around those loops and `fold` when a state transition can be expressed clearly as an accumulator. | `KEEP` / `EXCEPTION` | Order of events, random draws, and side effects are part of behavior; retain imperative loops when a pipeline would obscure those semantics. |
| Laravel paginated repository methods | `LengthAwarePaginator` contains framework-native page metadata/items. | Preserve paginator contract; do not wrap it in `Vec`. | `KEEP` / `EXCEPTION` | Callers use framework pagination links and totals; changing it would break the transport/view contract. |

## Implemented outcomes

- Repository methods returning multiple non-paginated records now use `Vec`:
  three market metadata lists, two data-import session lists, and successful
  market-type pair runs.
- Two lazy repository streams now use Exact `LazySeq`.
- Existing candle query output remains `Vec`; warmup and requested candles
  combine without converting back to Laravel Collection.
- Market-type result composition uses Exact `Query` for flattening, projection,
  and ordering; capital aggregation uses `Vec`.
- Evaluation page preparation uses Exact `Vec`, `Map`, and `Query::groupFold`
  for order/cost presentation metrics. Laravel `collect()` usage was removed
  from the results view.
- A follow-up pass over `app/Feature/` moved cluster analysis movement
  selection/sorting/chart projection, stress-test aggregation and scenario
  state, Monte Carlo sorting/histogram bucketing, Alpha Vantage key lookup and
  hourly grouping, and portfolio cost-component selection to Exact Query.
- Pagination, SQL-side filtering/ordering, queue dispatch, persistence arrays,
  provider data, and chart JSON contracts remain Laravel/native where they
  are the appropriate boundary.

## Performance, tests, and remaining work

The changed in-memory paths allocate Exact collection wrappers and
intermediate result sequences; SQL filtering remains in the database and
eligible-pair scans remain lazy. A focused runtime/memory benchmark is still
needed before making claims about the magnitude of the performance trade-off.

The complete PHPUnit suite passed: 196 tests and 989 assertions. Pint passed
for all changed PHP files. No static-analysis command or controlled
runtime/memory benchmark was run, so no performance improvement is claimed.

No Exact capability gap was required for the inspected changes: Exact v2.2.0
provides the used `Vec`, `Map`, `LazySeq`, and `Query` operations. No in-memory
join was introduced because the inspected data relationships are already
queried by Eloquent or represented in nested result snapshots.

The follow-up targets collection-bearing pipelines identified across
`app/Feature/`; it does not certify every method in all 174 inventoried
application source files as individually reviewed. Remaining explicit loops
were reviewed as payload validation/decoding, persistence batches, request
normalization, random sampling, or sequential domain state rather than
unconverted collection transformations.
