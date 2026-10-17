# Fluctuation stress testing

## Purpose and supported mechanism

The implemented stress test is a post-backtest price-excursion scenario over
recorded orders. It does not increase volatility across a new candle series,
move the full price path, perturb strategy parameters, change fees, or rerun
the strategy. Its parameter `x` scales an observed excursion for each closed
trade and adjusts that trade's exit outcome. Do not interpret it as a general
market stress engine.

The result request accepts optional numeric `stress_x >= 0`. The process
rejects non-finite or negative values and non-finite scaled excursions; there
is no configured upper bound on `x`. `x = 0` leaves baseline exits unchanged.

## Scenario construction

For each closed order with a close date, close price, and available excursion:

1. A baseline winner selects its adverse extreme, falling back to the
   observation closest to stop-loss.
2. A baseline loser or breakeven selects its favorable extreme, falling back
   to the observation closest to take-profit.
3. `excursion = abs(entryPrice - selectedExtremePrice)`.
4. `stressedDistance = x × excursion`.
5. The stressed exit is moved from the baseline close against the position:
   `stressedPrice = baselineClose - direction × stressedDistance`, where
   `direction` is `+1` for long and `-1` for short.
6. If that price reaches/passes the stop, the scenario close is the stop level
   adjusted by configured adverse slippage; otherwise the stressed price is
   used. The scenario date is the selected extreme's date when available,
   otherwise the baseline close date.

The new P&L is baseline net P&L plus
`direction × (scenarioClose - baselineClose) × filledPositionSize × pnlMultiplier`.
Existing baseline costs are not recomputed for the changed exit date or price.
The strategy, entry, quantity, and data path remain unchanged. Non-closed
orders and closed orders without usable excursion values are not given an
invented new outcome.

This mechanism uses an adverse move derived from this trade's recorded
extremes. It is not an independently sampled adverse price shock and not
equivalent to increasing volatility: volatility would alter the path and
potentially entry/exit triggers, which this analysis does not do.

## Output metrics and edge behavior

The analysis returns baseline and scenario net profit, expectancy (net profit
/ closed trades, zero for none), profit factor, maximum realized drawdown
amount, closed-trade and exit counts, and exit/profitability frequencies.
Profit factor is gross positive net P&L divided by absolute gross negative
net P&L. With gross losses, the ratio is returned. With no gross losses and
positive gross profits, the stress metric is absent (`null`) rather than
infinity; with neither gains nor losses it is `0`. Frequencies use closed
trades as the denominator, except stress-exit profitability frequencies,
which use the number of stress exits; a zero denominator yields 0%.

Trade statuses rejected by shared-capital allocation are excluded before
analysis. Results rely on the recorded excursion source and date; incomplete
quote or OHLC data can affect those observations. The scenario does not
recalculate per-order fees, financing, capital allocation, or subsequent
strategy decisions. It explores only the stated scalar scenario and does not
cover all possible market conditions.

## Verification and related documentation

Behavior is covered by
[`FluctuationStressTestDomainTest`](../../tests/Unit/Engine/Domain/FluctuationStressTestDomainTest.php)
and
[`StressTestMetricsTest`](../../tests/Unit/Engine/Domain/StressTestMetricsTest.php).
Related: [robustness analysis](robustness-analysis.md),
[performance metrics](performance-metrics.md), and
[Interpreting results](../user-guide/interpreting-results.md).
