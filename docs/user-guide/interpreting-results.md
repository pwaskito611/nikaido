# Interpreting Results

Backtest output is a deterministic calculation over the supplied candles and
configured assumptions. It is not evidence that a strategy will be profitable
in live trading. In particular, the supplied strategy can use a candle close
to decide an entry that is executed at that candle's open; this may introduce
look-ahead bias. Quote coverage, gaps, transaction costs, and intrabar
ambiguity also affect validity.

## Result records

The results view includes decision history, order history, a capital chart,
summary metrics, execution settings, and optional stress/Monte Carlo/cluster
analysis. Decision history shows the candle timestamp, signal, strategy
quantity, stop/target, and entry status. Order history includes entry/exit
dates, side, requested and executed quantities, entry/exit fills, stop/target,
costs, P&L, and close reason.

For market-type runs, inspect the persisted parent run and individual pair
records, including pair status and shared-capital allocation fields. A
partially failed market-type run must not be interpreted as if all selected
pairs completed successfully.

## Baseline performance measures

### Net profit / realized P&L

Order net P&L subtracts configured transaction and holding costs from gross
price movement. The capital result applies entry costs when fills occur and
realized exit P&L/costs when exits fill. `Ending capital` is not marked to
market: an open position's price change is not included as unrealized P&L.
Entry costs on an unclosed position may already have reduced capital.

### Closed trade count and win/loss

Closed-trade statistics use orders with a completed close. A profitable trade
has net P&L greater than zero, a losing trade is below zero, and breakeven is
exactly zero. Win rate is profitable closed trades divided by all closed
trades, expressed as a percentage; losses and breakeven trades remain in the
denominator. With no closed trades, rates resolve to zero in the stress
metrics or may be displayed as unavailable in other summary panels.

Trade count does not tell you how much time a position was exposed. An order
with multiple partial fills is still one order/trade in the closed-order
count.

### Profit factor

Profit factor is the sum of positive net trade P&L divided by the absolute sum
of negative net trade P&L. Breakeven trades do not affect either sum. When
there are gains but no losses, the stress result has no finite denominator
and reports an unavailable value; when both gross sums are zero, the stress
metric is zero. The ordinary result summary instead reports positive infinity
for gains with no losses and null when there are no closed trades or neither
gains nor losses. A high factor can come from very few trades and should be
read with the sample size.

### Expectancy

In stress-test metrics, expectancy is net profit divided by closed-trade
count; it is zero when there are no closed trades. It is an average amount per
closed order, not a risk-normalized measure or a guarantee for the next trade.

### Maximum drawdown

The capital chart and stress metric use realized capital movements rather
than a per-candle mark-to-market equity curve. Stress maximum drawdown is the
largest peak-to-subsequent-trough amount:

`drawdown = prior peak realized capital − subsequent realized capital`

The stress metric reports an amount, not a percentage. The ordinary result
summary also reports realized-equity drawdown measures. Neither captures
unrealized adverse movement within an open trade or intrabar drawdown between
recorded capital events. A zero drawdown can therefore coexist with meaningful
open-position exposure.

## Stress test

The fluctuation stress analysis compares baseline and scenario outcomes using
closed orders. It reports net profit, expectancy, profit factor, maximum
drawdown, trade and exit counts, and frequencies. Exit frequencies divide
each exit category by closed trade count; profitable/losing/breakeven stress
exit frequencies divide by the number of stress exits. A denominator of zero
produces a zero frequency. When there is no gross loss, profit factor may be
unbounded or unavailable.

The stress scenario uses each order's recorded excursion information to
perturb outcomes. It is a sensitivity check based on this backtest's observed
paths, not a new independent price history, a probability estimate, or a
promise that future adverse movement will be bounded by that scenario. For
stress profit factor, positive gross gains with no gross losses produce an
unavailable value; a zero/zero case produces zero. This differs from the
ordinary backtest profit-factor summary, which returns positive infinity for
gains with no losses and null when there are no closed trades or neither gains
nor losses.

## Cluster and Monte Carlo analyses

Cluster analysis reports results for chronological segments of the decision
history. It can help expose concentration in a particular part of the sample,
but a segment is not automatically a statistically independent market
regime.

Monte Carlo operates on historical closed-trade P&Ls:

- **Shuffle** reorders the observed trades without replacement.
- **Bootstrap** samples with replacement.
- **Fixed monetary P/L** applies sampled monetary results to the chosen initial
  capital without compounding the trade return.
- **Compounded return** applies each sampled trade's return sequentially to
  the simulated capital.

The results include distributions and drawdown-threshold outcomes across the
requested simulations. A fixed random seed allows repeatability. These
simulations rearrange or resample the observed trade outcomes; they do not
model new market prices, changing execution conditions, or dependencies among
trades beyond those captured by the selected method.

## How to compare and investigate results

Compare runs only when the dataset, pair/timeframe, date window, warm-up,
strategy implementation, capital mode, schedule, fees, quote data, slippage,
and fill assumptions are controlled. A performance change after switching
from general OHLC to bid/ask data may reflect execution prices, not a strategy
signal change.

For an unexpectedly strong result, inspect:

1. signal timestamp versus entry-fill timestamp and the close/open timing;
2. selected data's timezone, duplicate timestamps, gaps, and bid/ask coverage;
3. order fills, stop/target conflicts, and the stop-first same-candle rule;
4. gross versus net P&L and each fee component;
5. trade count, breakeven/loss denominator, holding duration, and exposure;
6. whether open positions or missing pair jobs are omitted from the apparent
   result; and
7. performance across clusters and reasonable stress assumptions.

A profitable baseline alone does not establish a robust edge, account for
all execution frictions, or guarantee future returns. Nikaido is a research
and strategy-testing framework; users remain responsible for evaluating their
methods and bearing any losses, including those arising from an error or bug
that leads to a mis-evaluation.

For exact run procedures, see [Running backtests](running-backtests.md). For
the actual order and fill semantics, see
[Orders and positions](orders-and-positions.md).
