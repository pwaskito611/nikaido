# Capital and risk

Capital, requested risk, position quantity, realized profit/loss, and equity
are different quantities in Nikaido. The simulation uses a capital value to
size strategy requests and update realized results, but it does not provide a
broker-style account, margin model, or mark-to-market valuation of open
positions.

The UI workflow is in
[Capital and position sizing](../user-guide/capital-and-position-sizing.md);
multi-pair mechanics are detailed in
[Shared-capital simulation](../features/shared-capital.md).

## Starting capital and the current strategy's sizing

Each pair simulation begins with the configured positive `initial_capital`.
For a single pair, that amount is assumed to be in the pair's quote
denomination; the application does not convert between currencies.
The current moving-average strategy requests risk equal to 1% of its
`StrategyExecutionContext::currentCapital`. For a reference entry price
`E`, stop price `S`, and P&L multiplier `M`, its requested quantity is
conceptually:

```text
requested risk = current capital × 0.01
quantity = requested risk / (abs(E − S) × M)
```

For `units`, `M = 1`; for contracts/lots, the configured contract size is
used in the quantity/P&L conversion. The current strategy sets its target at
two times the entry-to-stop distance. This is a code-level strategy rule, not
a user-configurable risk setting, and requested risk is not a guaranteed
maximum loss.

The process updates the strategy context's current capital as entry costs and
realized exit fills change capital. That allows later strategy decisions to
size from the updated amount. Actual fills, slippage, fees, gaps, and partial
execution can make the eventual realized result differ from the sizing
request.

## P&L, costs, and realized capital

For a long exit fill:

```text
gross P&L = (exit price − entry price) × exited quantity × P&L multiplier
```

For a short exit fill:

```text
gross P&L = (entry price − exit price) × exited quantity × P&L multiplier
```

Net realized P&L subtracts costs associated with that fill. Entry costs are
also charged to the order's cumulative realized P&L and deducted from capital
when entry fills occur. Exit and holding costs are charged when exit fills
occur. Partial fills accumulate on the same order; a completed trade's P&L
includes the recorded entry costs and its exit-fill results/costs.

Nikaido does not mark open positions to market. Ending capital includes
capital changes already applied, including entry fees, but not unrealized
P&L on an open position. No automatic close occurs at the end of the data
window. Accordingly, the result's capital movement/chart and ordinary
drawdown measures are based on realized exit events, not a continuous
mark-to-market equity curve. See [Performance metrics](../features/performance-metrics.md)
for exact metric definitions.

Position notional is not reserved or debited as margin in a single-pair
simulation. There is no general leverage, margin-call, insufficient-cash,
maximum-position, or liquidation policy; costs and losses can reduce capital
below zero. The quantity unit and P&L multiplier are not an FX conversion:
prices, costs, capital, and P&L are treated as directly comparable numeric
amounts.

## Multiple pairs: separate versus shared capital

Market-type runs execute pair simulations independently. In `separate`
mode, each pair starts with the configured capital; the overall reporting
timeline combines their recorded capital effects chronologically.

In `shared` mode, pair strategies still generate their decisions and local
order histories independently. Afterward, the portfolio synchronizer replays
events in timestamp order. At the same timestamp, exits release risk and
update capital before new entry requests are allocated. Existing open
stop-risk is reserved; available allocation capital is effectively
`max(0, shared capital − reserved risk)`. Fixed entry fees are accounted for
before allocation, variable entry costs contribute to demand, and competing
requests can be scaled proportionally. A zero allocation can reject an entry.

Risk reservation is not a debit of position notional or a margin calculation.
Shared mode does not rerun the strategy using synchronized portfolio capital
at every event, convert pair quote currencies, or model broker allocation.
Only combine pairs whose capital, risk, fees, and P&L amounts have compatible
denominations. Pair jobs can have simultaneous positions; each individual
pair simulation still has at most one active position.

Neither mode establishes account solvency. Shared capital can be affected by
fees and realized losses, and there is no automatic liquidation policy.
Missing candles are not interpolated, so a pair with no event at a particular
timestamp contributes no event then.

Related: [Backtesting fundamentals](backtesting-fundamentals.md),
[Multi-pair backtesting](../features/multi-pair-backtesting.md), and
[Market support](../features/market-support.md). The persisted and
computation paths are diagrammed in
[Application flows](../architecture/application-flow.md).
