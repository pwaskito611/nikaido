# Multi-pair backtesting

## Purpose and availability

A market-type run schedules one backtest job per eligible pair, then combines
the pair histories for review. Eligibility is based on pair membership and
matching timeframe candles inside the requested date range; pairs without
in-range data are skipped. Explicitly excluded pair IDs are also skipped.
Pairs whose data exists but has no candle in the selected active hours/days
can still fail when their individual run starts.

Market-type runs require an asynchronous queue connection. The action rejects
the `sync` queue driver instead of executing jobs inline. Pair jobs use the
same configured-pair action as a single-pair run. This is a batch research
workflow, not one strategy instance that receives all instruments together.

## Per-pair execution and uneven history

Each job loads and runs one pair's candles in its own chronological order,
with up to 200 warm-up rows, its own single-pair process state, and one
strategy-execution context initialized from the configured initial capital.
The engine permits one active order/position per pair. Pair histories can
begin or end at different timestamps: there are no synthetic candles for
missing intervals and no requirement that every pair has an event on every
timestamp.

The separate pair jobs are independent simulations. Strategy decisions are
not jointly generated from a synchronized multi-symbol context. In shared
capital mode, the decisions and local orders are produced first and then
their resulting entry/exit events are replayed through a portfolio capital
synchronizer. This allocation step does not rerun the strategy at each
portfolio timestamp.

## Ordering and attribution

Aggregation sorts decision rows by candle timestamp and pair name. Order rows
are sorted by close time, then pair name and order ID; unclosed orders sort
after dated closes. Shared-capital synchronization processes all exits at a
timestamp before new entries, then uses pair name, order ID, and stable event
sequence to break ties. Thus different historical starting dates are handled
as actual event times, not by waiting for one pair's entire history to finish.

Each aggregate decision and order retains a `pair` label. The combined output
also includes successful-pair count, per-pair average initial/ending capital,
capital mode, and overall initial/ending capital. Shared capital uses the one
configured initial-capital amount. Separate mode starts with the sum of
successful pair initial capitals and replays recorded capital changes
chronologically; the mean per-pair values are also reported.

## Shared and separate capital

In `separate` mode, each pair backtest starts from the configured capital and
there is no cross-pair risk reservation. The combined timeline sums capital
effects by event time. In `shared` mode, the synchronizer adjusts pair entry
quantities against one capital/risk budget, reserves allocated stop-risk while
positions remain open, adds realized exit P&L, subtracts costs, and releases
risk as exit fills occur. Same-time exits release risk before same-time entry
requests are allocated. If total eligible demand is over available risk
capital, the allocation factor is applied proportionally. Details and
assumptions are in [shared capital](shared-capital.md).

Shared mode does not convert quote currencies, apply margin/leverage, or
introduce an exchange allocation model. Only combine instruments whose
capital and P&L amounts are meaningfully comparable.

## State, failures, and reproducibility

The parent run and pair-run records persist the configuration, statuses,
results, and failure message. Pair statuses are `pending`, `running`,
`succeeded`, and `failed`; a parent can finish as `completed` or
`completed_with_failures`. Aggregation uses successful pair runs, so inspect
the success/failure counts before interpreting an overall result. An empty
eligible set is completed immediately with zero pairs; it is not a completed
backtest with zero trades.

Given identical per-pair inputs and code, the pair simulations are repeatable.
Queue completion order does not determine shared-capital allocation: events
are sorted by their candle/fill timestamps and deterministic tie keys before
the portfolio replay. Monte Carlo randomness, if separately requested, is
not part of this run.

## Verification and related documentation

See
[`MarketTypeBacktestTest`](../../tests/Feature/MarketTypeBacktestTest.php),
[`PortfolioCapitalSynchronizationDomainTest`](../../tests/Unit/Workbench/Domain/MarketTypeBacktests/PortfolioCapitalSynchronizationDomainTest.php),
and
[`CombineMarketTypeBacktestResultsActionTest`](../../tests/Unit/Workbench/Actions/MarketTypeBacktests/CombineMarketTypeBacktestResultsActionTest.php).
The user workflow is in [Running backtests](../user-guide/running-backtests.md).

Related: [shared capital](shared-capital.md),
[concurrency](concurrency.md), [market support](market-support.md), and
[backtest engine](backtest-engine.md).
