# Running Backtests

This guide covers the research workflow for intraday and swing strategy ideas
using your own historical CFD or spot price data. Start with the local setup
in [Getting Started](../getting-started/installation.md). The current
Workbench uses the strategy wired in the application; there is no per-run
strategy selector or parameter editor. Strategy-period and risk-rule changes
require code changes and a controlled comparison. Review
[Market support](../features/market-support.md) before treating a spot run as
an account-level simulation.

## Start the application and jobs

From the project root, start the local application engine (web server and
background jobs) with:

```sh
php artisan nikaido:start
```

Stop it with:

```sh
php artisan nikaido:stop
```

The single-pair backtest runs in the web request without a PHP execution time
limit; the request may therefore remain open for a long run. Web server,
reverse-proxy, client, and other infrastructure timeouts may still apply.
Market-type runs are queued, so the jobs worker started by the engine must
remain available. Check that the configured database is reachable and
migrations are complete before opening the Workbench. Database connection
errors are infrastructure errors, not empty-history results.

## Prepare data and run a single pair

1. Confirm the pair, timeframe, and historical coverage in Market Prices.
   Verify warm-up data before the chosen start date; the current strategy
   needs up to 200 previous candles.
2. Open `/workbench/tests/create` and select **Specific pair test**.
3. Select the market pair and configured timeframe, then set a date/time
   start and end that are covered by the data.
4. Enter positive initial capital. It is not a user-editable position-size
   rule; the strategy determines quantity and requested risk.
5. Select **Run all day** or set active times and excluded ISO weekdays.
   Strategy signals are generated only while the configured schedule is
   active, but existing positions continue to be monitored on candles outside
   those entry hours.
6. Decide whether to include CFD dividend adjustments, fixed slippage,
   synthetic partial fills, commission, and overnight financing. Adjustments,
   slippage, and partial fills default to off.
7. Submit the form. The browser displays the results for the run.

The active schedule's time-of-day window can cross midnight. Keep its
time-zone interpretation aligned with the timezone convention used in the
stored timestamps.

## Run a market type with multiple pairs

Select **Market type test**, choose the market type and timeframe, then
configure the same date range, initial capital, schedule, execution assumptions,
and costs. Choose **Shared portfolio capital** to allocate entry risk across
pair results, or **Separate capital per pair** to keep independent accounts.
Pair jobs still generate each pair's history independently. Shared mode then
synchronizes their events by timestamp, releasing risk on exits before
allocating same-time entries; competing entry risk is reduced proportionally
when the available shared risk capital is insufficient.

Use shared capital only when pair prices, notional, and P/L use compatible USD
normalization. Pair jobs are synchronized using
their actual candle timestamps; missing periods are not filled with synthetic
events. Multiple runs queue in the background.

Use the run page to track the parent run and its pair runs. Pair statuses are
`pending`, `running`, `succeeded`, or `failed`. A pair failure is reported in
the run state rather than being a valid zero-trade result. Review the failed
pair and application logs before comparing results. Shared-capital results
depend on all relevant pair histories and the global event order.

## Configure and preserve an experiment

Record at least:

- strategy code version and fixed strategy parameters;
- pair IDs, timeframe ID, and data source/version;
- exact start/end and timestamp convention;
- starting capital and shared/separate capital mode;
- active hours and excluded weekdays;
- cash-flow inclusion and the source/version of per-pair events;
- slippage enabled/value/pip size;
- partial-fill ratio and remainder policy; and
- every enabled fee component, method, rate, and applicable side.

When dividend adjustments are enabled, only confirmed or paid events known by
the ex-date are recognized for a position held before the ex-date candle.
Long and short positions receive opposite USD adjustments. The net adjustment
total is included in ending capital and shown separately in results.

Only change one factor at a time when attributing a result difference.
Changing the input price history, warm-up coverage, timeframe, timezone
convention, strategy code, or event participation invalidates a direct
performance comparison even if the visible date range is unchanged.

## Analysis options

On a completed result, the analysis form can optionally calculate:

- **Fluctuation stress test**: uses the stored favorable/adverse excursion
  information and a selected stress parameter to recalculate closed-trade
  outcomes; this is a scenario, not an alternate historical execution.
- **Monte Carlo**: resamples the historical closed-trade P&L sequence using
  `shuffle` (without replacement) or `bootstrap` (with replacement). It accepts
  a simulation count (1–10,000), optional trades per simulation (1–10,000),
  initial capital, risk model (`fixed_monetary_pl` or `compounded_return`),
  optional random seed, and drawdown threshold percentage.
- **Cluster analysis**: divides the decision history into a selected number
  of chronological clusters (2–10) and reports cluster-specific outcomes.

Use a random seed when you need repeatable Monte Carlo resampling. The
analysis options do not change the original backtest execution or repair
missing market data.

## Find results and diagnose failures

Single-pair results are retained in the browser session for the result
workflow; they are not a durable saved-run history. Save relevant settings and
results externally if needed for research reproducibility. Market-type runs
have persisted run and per-pair records and can be reopened from the run
history.

If a result shows zero trades, first verify that candles were loaded in the
requested range, enough warm-up exists, active hours/days include signal
candles, and the strategy produced valid decisions. A no-trade result is not
the same as a failed job. If a run errors, inspect `laravel/storage/logs/laravel.log`,
the run/pair status, database reachability, and malformed or incomplete prices.

For details on data validation and warm-up, see
[Market data](market-data.md). For interpreting the metrics, see
[Interpreting results](interpreting-results.md).
