# Queued execution and concurrency

## What can execute concurrently

Single-pair backtests execute synchronously in the web request. A market-type
run creates one queued job for each eligible pair. With multiple queue
workers, separate pairs may run concurrently; each individual pair simulation
still walks its own candles sequentially. Nikaido does not parallelize candles
within one pair or run a single strategy instance concurrently across
instruments.

The market-type initiator refuses the `sync` queue driver. The configured
queue must be asynchronous and a worker must be available. The default
development startup starts a worker; see [Installation](../getting-started/installation.md)
and [Running backtests](../user-guide/running-backtests.md). The custom
development process commands and relevant Laravel queue operations are listed
in [Commands](../reference/commands.md).

## Work partitioning and synchronization

The parent action creates a persisted parent run and pending pair-run records
inside a database transaction, in batches of up to 250 records. It then
dispatches one `RunMarketTypeBacktestPairJob` per pending pair. A job reloads
the saved configuration, adds its pair ID, and invokes the configured
single-pair action. Pair results are stored as JSON in each pair-run row.

Pair job completion order does not define combined capital order. On result
aggregation, pair order histories are reconstructed into events sorted by
timestamp, with exits before entries at equal timestamps and stable
pair/order/event tie breaks. Shared-capital allocation is a sequential replay
of those events after pair jobs complete; it is not concurrent mutation of a
shared live capital object. See [multi-pair backtesting](multi-pair-backtesting.md)
and [shared capital](shared-capital.md).

## Locks, transactions, retries, and failures

Each pair job uses Laravel's `WithoutOverlapping` middleware with a lock key
based on pair-run ID, release after 10 seconds, and expiry after 75 seconds.
The job timeout is 60 seconds, it allows three tries, and its backoff schedule
is 5, 15, and 30 seconds. Already-terminal (`succeeded` or `failed`) pair
records are no-ops on duplicate delivery. Result recording locks the pair-run
row in a DB transaction, avoids overwriting terminal results, updates the
parent under lock, and marks it completed only when terminal pair count
matches the parent total.

Dispatch exceptions are reported and mark only that pair failed. A job's
failure callback reports the exception and records a generic failure message;
the application log is needed for diagnostic detail. A run with failed pairs
finishes as `completed_with_failures`, retaining successful pair results.
There is no guarantee that an asynchronous queue backend, database, or worker
is itself available, and the feature does not provide automatic repair for
stale infrastructure or partial external failures.

The queue connection's retry/visibility settings must be compatible with job
runtime and lock expiry. Correctness is guarded at the pair-run record and
aggregation boundaries, but multiple queue workers alone do not guarantee
correct parallel portfolio behavior; the timestamp replay is what defines
the final shared-capital allocation.

## Operational verification and limitations

Use multiple workers only when the selected queue backend and database can
support concurrent jobs. Check parent/pair statuses, failure messages, queue
logs, and Laravel logs before treating an overall result as complete. An
empty eligible set completes immediately with zero pair jobs, while a failed
pair is distinct from a zero-trade success.

Concurrency and duplicate-delivery behavior are tested in
[`MarketTypeBacktestTest`](../../tests/Feature/MarketTypeBacktestTest.php),
including synchronous queue rejection and a terminal duplicate delivery.
Ordering and allocation tests are in
[`PortfolioCapitalSynchronizationDomainTest`](../../tests/Unit/Workbench/Domain/MarketTypeBacktests/PortfolioCapitalSynchronizationDomainTest.php).

Related: [multi-pair backtesting](multi-pair-backtesting.md),
[shared capital](shared-capital.md), and
[Running tests](../getting-started/running-tests.md). The end-to-end request
and job path is diagrammed in
[Application flows](../architecture/application-flow.md#market-type-queued-backtest).
