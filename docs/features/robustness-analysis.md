# Robustness analysis

## Purpose and available analyses

Nikaido offers three optional, post-backtest views that can help expose
sensitivity in observed results:

| Analysis | What it changes or partitions | Reruns the strategy? |
|---|---|---|
| Fluctuation stress | Reprices selected closed-trade exits using observed excursion data and scalar `stress_x`. | No |
| Chronological clusters | Partitions decision history into 2–10 ordered slices and reports segment results. | No |
| Monte Carlo | Shuffles or bootstraps closed-trade P&L outcomes into simulated sequences. | No |

There is no aggregate robustness score, pass/fail stability threshold,
parameter sweep, alternate-data generation, or statistical significance
test. The application calculates result summaries; it does not declare a
strategy robust.

## Baseline and comparison semantics

The baseline is the result of the already-completed backtest. Stress scenarios
operate on its recorded closed orders and excursions; costs and original
execution are not fully recomputed. Cluster analysis segments its decision
history and associates orders using open/exit timestamps. Monte Carlo samples
the closed orders' net P&L and, for the compounded model, their recorded
capital-at-open values.

Each method answers a different conditional question:

- Stress asks how selected recorded trade exits change under the implemented
  excursion factor.
- Clusters show whether the result is concentrated in chronological parts of
  this sample; the boundaries are equal-count decision slices, not detected
  regimes.
- Monte Carlo shows how the same observed trade outcomes could aggregate in
  randomized order or bootstrap samples under its sampling and capital model.

These analyses do not produce new price histories, signals, or fills. Their
outputs remain conditional on the original data, fixed strategy implementation,
close/open timing, quote coverage, cost assumptions, and order lifecycle.
  For shared-capital results, the current Monte Carlo path does not filter
  pair-local orders marked rejected by portfolio allocation; those outcomes can
  be sampled even though ordinary metrics, cluster analysis, and fluctuation
  stress exclude them. See the detailed
  [Monte Carlo caveat](monte-carlo.md#purpose-and-method).

## Sensitivity, sample size, and interpretation

An apparently stable aggregate may conceal few trades, dependence on a small
part of history, or an unrealistic execution assumption. A large Monte Carlo
simulation count does not create more independent market observations: it
reuses the same historical trade set. Bootstrap treats each sampled trade as
an independent draw and does not preserve serial correlation, regime changes,
or volatility clustering. Shuffle preserves all trade outcomes but discards
their time order.

Cluster summaries can identify variation across the sample but do not
establish independent market regimes. Trades can be associated with a cluster
if their open or exit-fill timestamp falls within its decision-time bounds, so
a position spanning segment boundaries can appear in more than one segment.
Stress testing only explores its selected excursion factor; it cannot bound
future losses. No one analysis establishes robustness or future profitability.

For independent research, users may compare controlled runs across data
periods, cost assumptions, or strategy parameters, but those comparisons are
research procedures external to the current automated feature. Change one
assumption at a time and verify that warm-up, pair/timeframe coverage, and
execution settings remain comparable.

## Configuration and verification

The result query accepts `stress_x` as a finite non-negative number. `clusters`
can select 2–10 partitions; invalid values fall back to the full history.
Monte Carlo accepts `shuffle` or `bootstrap`, 1–10,000 simulations, optional
trades per simulation (1–10,000), positive initial capital, optional seed
0–2,147,483,647, one of two risk models, and drawdown threshold 0–100%.
Exact Monte Carlo sampling behavior is documented in
[Monte Carlo](monte-carlo.md).

Relevant verification: [stress tests](../../tests/Unit/Engine/Domain/FluctuationStressTestDomainTest.php),
[Monte Carlo tests](../../tests/Unit/Engine/Domain/MonteCarloSimulationDomainTest.php),
and
[`AnalyzeBacktestResultsActionTest`](../../tests/Unit/Workbench/Actions/Backtests/AnalyzeBacktestResultsActionTest.php).
Related: [Interpreting results](../user-guide/interpreting-results.md),
[performance metrics](performance-metrics.md), and
[stress testing](stress-testing.md).
