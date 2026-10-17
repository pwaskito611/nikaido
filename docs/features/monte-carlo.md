# Monte Carlo trade-sequence analysis

## Purpose and method

Nikaido's Monte Carlo analysis samples the closed-trade net P&L records from
an already-completed backtest and evaluates simulated equity sequences. It
does not resample candle returns, generate new price paths, perturb strategy
parameters, or rerun the strategy and execution engine.

Two methods are available:

- `shuffle` permutes every historical closed trade exactly once per
  simulation. A configured `trades_per_simulation`, if supplied, must equal
  the number of closed trades.
- `bootstrap` samples historical trades uniformly with replacement. The
  sample can be shorter or longer than the historical set, within the
  configured limit, and can repeat or omit individual outcomes.

The source population is closed orders with numeric P&L; open orders are not
sampled. There is an important shared-capital edge case: the current analysis
preparation does not filter out pair-local orders marked
`portfolio_entry_rejected`, so those outcomes can be included in Monte Carlo
even though ordinary performance metrics, cluster analysis, and fluctuation
stress exclude them. Interpret shared-capital Monte Carlo output cautiously.

## Configuration and validation

| Request key | Accepted value/default |
|---|---|
| `simulation_count` | 1–10,000; required when enabled |
| `trades_per_simulation` | Optional 1–10,000; defaults to historical count |
| `method` | `shuffle` or `bootstrap`; required when enabled |
| `mc_initial_capital` | Positive finite number; required when enabled |
| `random_seed` | Optional integer 0–2,147,483,647 |
| `risk_model` | `fixed_monetary_pl` or `compounded_return`; required when enabled |
| `drawdown_threshold` | 0–100 percent; required when enabled |

The config object defaults to 1,000 simulations, initial capital 10,000,
`shuffle`, `fixed_monetary_pl`, no explicit seed, and a 20% threshold when
constructed directly. The result-page request requires values for the
simulation count, method, initial capital, risk model, and threshold when
`monte_carlo` is accepted/enabled; the initial-capital request field is named
`mc_initial_capital`.

The implementation rejects an empty numeric closed-trade sample, any closed
trade without numeric P&L, a shuffle sample size unequal to historical count,
and workloads where `simulation_count × trades_per_simulation` exceeds
10,000,000 trade evaluations. Compounded return additionally requires a
positive finite `capital_at_open` for every sampled trade. At the request
boundary, the simulation fields are required when the Monte Carlo checkbox is
enabled.

With a seed, the process uses PHP's `Mt19937` through `Randomizer` and produces
repeatable samples for the same ordered input and runtime implementation.
Without a seed, it draws a random seed. Seeded results are reproducible
simulation outcomes, not evidence that the model's assumptions are valid.

## Equity and drawdown calculations

For fixed monetary P&L, each sampled trade updates simulated equity by
`nextEquity = equity + tradeNetPnl`. For compounded return, the recorded
trade return is `tradeNetPnl / capitalAtOpen`, and the update is
`nextEquity = equity × (1 + tradeReturn)`. If equity becomes non-positive,
the sequence is stopped, final equity is set to zero, and maximum drawdown is
100% of peak equity (amount equal to the peak).

For each sequence, maximum drawdown amount is peak equity minus subsequent
equity; percent is that amount divided by peak equity. Outputs include final
equity mean, median, p5, p25, p75 and p95; maximum drawdown amount and percent
median, p95 and worst; and ten-bin histograms for final equity and drawdown
percent. Probability of loss is the percentage of simulation final equities
below initial capital. Probability of drawdown is the percentage whose
maximum drawdown percent is greater than or equal to the configured threshold.

## Interpretation and limitations

Shuffle isolates order sensitivity while retaining the observed trade set.
Bootstrap adds sampling variability by drawing with replacement, but both
methods discard market-path information. In particular, trade outcomes are
treated as exchangeable/independent draws for bootstrap and no market regime,
serial dependence, exposure duration, execution changes, or price paths are
modeled. Fixed monetary P&L does not compound; the compounded model uses each
trade's recorded local or synchronized capital-at-open as its return
denominator and compounds those percentages on simulated capital.

Results depend on trade count and the original backtest's costs and fill
assumptions. A narrow output distribution from a small or homogeneous sample
does not prove robustness. Histograms and empirical quantiles describe only
these simulated resamples; they are not calibrated forecasts or confidence
intervals for future market returns.

## Verification and related documentation

Tests cover shuffle, bootstrap, reproducibility, compounded returns,
drawdown summaries, and invalid samples in
[`MonteCarloSimulationDomainTest`](../../tests/Unit/Engine/Domain/MonteCarloSimulationDomainTest.php)
and
[`MonteCarloResultTypesTest`](../../tests/Unit/Engine/Domain/MonteCarloResultTypesTest.php).
Related: [robustness analysis](robustness-analysis.md),
[performance metrics](performance-metrics.md), and
[Interpreting results](../user-guide/interpreting-results.md).
