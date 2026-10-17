# Robustness and stress testing

Nikaido's optional analyses examine different aspects of an already-completed
backtest. They do not replace its historical prices, regenerate signals, or
rerun the strategy. None of them is a pass/fail test of future profitability
or proof of live-market robustness.

| Analysis | What it changes or partitions | New strategy run? |
|---|---|---|
| Fluctuation stress | Adjusts selected closed-trade exits using a scalar multiple of recorded excursion. | No |
| Chronological clusters | Partitions decision history into contiguous time-ordered groups. | No |
| Monte Carlo shuffle | Reorders the same closed-trade outcomes. | No |
| Monte Carlo bootstrap | Resamples closed-trade outcomes with replacement. | No |

Exact request fields and feature behavior are documented in
[Robustness analysis](../features/robustness-analysis.md),
[Fluctuation stress testing](../features/stress-testing.md), and
[Monte Carlo](../features/monte-carlo.md).

## Fluctuation stress is not volatility stress

For each closed order with a usable recorded excursion, the analysis selects
an adverse extreme for a baseline winner (falling back to the observation
closest to its stop) or a favorable extreme for a baseline loser/breakeven
(falling back to the observation closest to its target). With `x >= 0`, it
computes:

```text
excursion = abs(entry price − selected observed extreme)
stressed distance = x × excursion
```

It moves the recorded baseline exit against the position by that distance.
If the resulting price reaches/passes the stop, the scenario uses the stop
with configured adverse slippage; otherwise it uses the stressed price.
Scenario P&L adjusts baseline net P&L by the changed exit price, filled
quantity, direction, and P&L multiplier.

The strategy, entry, quantity, data path, and baseline costs are not
recomputed. No new candles or intrabar path are generated. Costs are not
recalculated for the changed exit price/date. Orders without a closed outcome
or usable excursion are not assigned invented outcomes. The parameter scales
an observed excursion; it is not a probability, a volatility multiplier over
the candle series, or an independently sampled adverse shock.

There is no automated transaction-cost stress sweep or strategy-parameter
sensitivity sweep. Users can run controlled separate backtests with different
fee or strategy settings, but those comparisons are external to these
post-backtest analyses and must control other inputs.

## Chronological clusters

When selected, the decision list is split into 2–10 contiguous slices using
floor-based, nearly equal decision-count boundaries. These are chronological
subsets, not statistically detected regimes or independent samples. Orders
are associated by whether their open date or any exit-fill date falls within
a slice's timestamp bounds. A position spanning a boundary can therefore
appear in more than one cluster.

Cluster summaries can help reveal that results are concentrated in a
particular portion of this backtest. They cannot show that the boundaries
represent market regimes or establish out-of-sample stability.

## Monte Carlo trade-sequence analysis

Monte Carlo samples closed-trade net P&L from the completed result:

- **Shuffle** permutes every historical trade once per simulation. It keeps
  the sample and total trade P&L but removes its original order.
- **Bootstrap** draws historical trades uniformly with replacement. A
  simulation may repeat or omit outcomes and can use a configured sample
  length.
- **Fixed monetary P&L** adds each sampled trade's fixed amount to simulated
  capital.
- **Compounded return** uses `trade P&L / recorded capital at open` as a
  return and applies it to the simulation's current capital.

The process reports final-equity summaries, maximum drawdown distributions,
probability of ending below starting capital, and probability of reaching
the configured drawdown threshold. A random seed makes the sampling
repeatable for the same ordered input and runtime. It does not validate the
sampling assumptions.

This analysis does not sample candles or market returns, preserve serial
dependence, regimes, volatility clustering, exposure duration, or
execution-path effects. Bootstrap treats the historical trades as
exchangeable independent draws. More simulations do not add independent
market observations.

There is an implementation inconsistency for shared-capital runs:
ordinary performance metrics, clusters, and fluctuation stress exclude
portfolio entries marked rejected, but the current Monte Carlo preparation
filters by closed date and numeric P&L without excluding those marked
rejected. Such a rejected pair-local outcome can therefore enter the Monte
Carlo sample. Treat shared-capital Monte Carlo summaries cautiously until
that behavior is resolved.

## Reading results cautiously

These analyses answer conditional questions about recorded outcomes and
recorded excursions only. Small trade samples, omitted execution realities,
look-ahead timing, data quality, parameter selection, and the choice of
scenario can dominate an apparently stable result. Stress outputs and Monte
Carlo quantiles are not calibrated future-loss limits or confidence
intervals for future returns.

For the definitions and edge cases of result metrics, see
[Performance metrics](../features/performance-metrics.md). For baseline
result interpretation, see
[Interpreting results](../user-guide/interpreting-results.md). The analysis
entry point and post-run result flow are shown in
[Application flows](../architecture/application-flow.md).
