# Market Exploration

Market Exploration lets you inspect stored OHLC candles for one market pair
and timeframe, then run the strategy currently wired into the backtest engine
over the same selected date range. It is a lightweight visual check, not a
replacement for the full backtest results and evaluation workflow.

## Load a market chart

1. Open **Workbench → Market exploration**.
2. Optionally type part of a symbol, such as `EUR`, in **Search pair**. The
   Pair list is filtered as you type.
3. Select a pair and timeframe.
4. Choose **From** and **To** dates, then select **Load chart**.

The chart displays the selected pair/timeframe candles within the date range.
Dates include the full calendar day, from `00:00:00` through `23:59:59`.
Only stored candles are shown; missing timestamps are not filled. If no candles
match, check the selected pair, timeframe, date range, and stored market data.
See [Data management](data-management.md) to add or import market data.

The chart can be moved horizontally by scrolling or dragging. Its price scale
adjusts to the candles currently visible, so lower-volatility sections remain
readable. Hover over the chart to inspect a candle's OHLC values.

## Run the strategy

After loading a chart with candles, select **Run strategy**. The application
runs the currently configured strategy over the selected pair, timeframe, and
date range using the backtest engine. It also loads preceding candles as
strategy warm-up where available; warm-up candles are not included in the
displayed chart or requested-period trade summary.

There is no strategy selector or parameter editor on this page. The strategy
is the same implementation currently wired to the engine in
[`src/main.php`](../../src/main.php). Its logic and fixed parameters are
code-configured; see [Strategies](strategies.md) for the current implementation
and decision contract.

Market Exploration uses the engine's default execution settings: initial
capital of 10,000, full-day activity, and disabled slippage, partial fills,
and additional trade costs. These assumptions can affect results. Use the
full **Run a test** workflow when you need to configure execution assumptions
or inspect detailed results.

## Read the summary and chart markers

After a successful run, the page displays:

- **Trades** — orders with at least one filled entry.
- **Wins** — fully closed trades whose realized profit/loss is positive.
- **Losses** — fully closed trades whose realized profit/loss is negative.
- **Closed trades** — trades whose entire position has been exited. A
  break-even closed trade is included here, but is neither a win nor a loss.

Open or partially closed positions count as trades but not as closed trades.
These are basic counts only; the page does not show the full evaluation
metrics, costs, or order history.

Markers are drawn at the recorded entry and exit fill prices:

| Marker | Meaning |
|---|---|
| Yellow circle with `E ↑` | Long entry |
| Yellow circle with `E ↓` | Short entry |
| Blue square with `C ↑` | Close of a long position |
| Blue square with `C ↓` | Close of a short position |

A position may have multiple fill markers when it is filled in parts. Markers
are shown only when their fill timestamp matches a candle in the selected
chart range.

## Understand the result's scope

The summary and markers use the backtest engine's simulated orders and fills;
they are not broker executions or live-trading instructions. A run with no
trades may mean the configured strategy did not generate valid entries in the
selected period. Check data coverage, timeframe, date range, and warm-up
history before drawing conclusions. Historical results do not guarantee
future performance.

For configurable single-pair or market-type runs and detailed analysis, see
[Running backtests](running-backtests.md) and
[Interpreting results](interpreting-results.md).
