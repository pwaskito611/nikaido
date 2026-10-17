# Performance metrics

## Scope and accounting basis

The ordinary result analysis calculates closed-trade count, profit factor, and
average, median, and maximum drawdown percentage. Cluster summaries expose
order/closed/win/loss counts, win rate, net realized P&L, and start/end
capital. Trade-level gross and net realized P&L and fill costs are also
recorded. Metrics exclude portfolio entries rejected by shared-capital
allocation where the analysis layer filters them.

Nikaido does not calculate every conventional performance or risk statistic.
In particular, the audited result/analysis path has no Sharpe or Sortino ratio,
CAGR, annualized return, exposure-adjusted return, or volatility estimate.
Metric absence must not be interpreted as a zero value.

## Definitions and edge cases

### Trade P&L and count

For a long fill, gross P&L is
`(exitPrice - entryPrice) × exitedQuantity × pnlMultiplier`; for a short it is
`(entryPrice - exitPrice) × exitedQuantity × pnlMultiplier`. Net realized
P&L subtracts that fill's exit costs. Entry costs are also subtracted from
the order's cumulative realized P&L when entry fills occur. An order becomes
a closed trade only when remaining position quantity is zero. Multiple
partial fills still comprise one position-level order. An open position has
no completed `profit_loss` and contributes no closed-trade result.

### Profit factor

The formula is
`sum(max(net closed-trade P&L, 0)) / abs(sum(min(net closed-trade P&L, 0)))`.
It uses net trade P&L, including recorded entry and exit/holding costs, and
not the gross price-movement field. With no closed trades, the ordinary
summary returns `null`. With positive gains and no losses it returns positive
infinity; with no gains and no losses it is `null`. A high value based on few
trades is not strong statistical evidence.

### Realized drawdown

Drawdown uses capital snapshots after exit fills, ordered by event time and
stable sequence, with initial capital as the starting peak. For each snapshot
below the running peak:

`drawdownPercent = (runningPeakCapital - observedCapital) / runningPeakCapital × 100`

Maximum drawdown is the largest such percentage. Average and median drawdown
are calculated over observations below the running peak only; observations at
a new/equal peak are not added as zero drawdowns. If there are no below-peak
observations, average, median, and maximum are all zero. If the peak is
non-positive, later observations are skipped for percentage drawdown.

These are realized-event drawdowns, not mark-to-market equity drawdowns.
They omit intrabar movement and unrealized exposure. An entry cost on an
unclosed position can reduce ending capital without creating a drawdown
observation until an exit movement is recorded.

### Cluster summaries

Cluster analysis divides the ordered decision history into `k` nearly equal
contiguous slices using floor boundaries: cluster `i` spans decision indexes
`floor((i-1)N/k)` through (but not including) `floor(iN/k)`. The selected
cluster's timestamp bounds determine its order selection: an order is included
if its open date or any exit-fill date falls inside those bounds. Consequently,
an order spanning a boundary can be associated with more than one cluster.
Cluster win rate is profitable closed orders / closed orders × 100; with no
closed orders it is zero. The summary's net P&L includes realized P&L for
orders, including partial exits, and uses the cluster's reconstructed
realized-capital timeline.

Cluster count is accepted from 2 through 10; an unrecognized query value
falls back to the full result rather than reporting a validation error.
Clusters are chronological slices, not detected regimes or independent
samples.

## Separate analysis outputs

The optional fluctuation stress analysis reports baseline/scenario net profit,
expectancy, profit factor, maximum drawdown amount, trade and exit counts,
and frequencies. Its formulas and empty-denominator behavior are detailed in
[Stress testing](stress-testing.md).

Monte Carlo reports final-equity mean, median, p5/p25/p75/p95; maximum
drawdown amount and percent median/p95/worst; probability of loss; probability
of reaching a drawdown threshold; and histograms. These are distributions of
sampled historical trade outcomes, not backtest-time strategy metrics. See
[Monte Carlo](monte-carlo.md).

## Interpretation and verification

Net trade P&L and ending capital are not interchangeable: capital can include
entry costs already charged for open orders, while an open trade has no
realized exit P&L. Nor does a zero realized drawdown prove the strategy had no
adverse intratrade movement. Historical profitability does not establish
future profitability or robustness.

Metric formulas are implemented in
[`AnalyzeBacktestResultsAction`](../../app/Feature/Backtest/Action/AnalyzeBacktestResultsAction.php),
[`BacktestClusterAnalysisProcess`](../../app/Feature/Backtest/Process/BacktestClusterAnalysisProcess.php),
and
[`FluctuationStressTestProcess`](../../app/Feature/Backtest/Process/FluctuationStressTestProcess.php).
Related tests include
[`AnalyzeBacktestResultsActionTest`](../../tests/Unit/Workbench/Actions/Backtests/AnalyzeBacktestResultsActionTest.php)
and
[`StressTestMetricsTest`](../../tests/Unit/Engine/Domain/StressTestMetricsTest.php).
For user-facing result guidance see
[Interpreting results](../user-guide/interpreting-results.md).
