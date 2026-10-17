# Orders and Positions

The backtest does not model a broker connection. It converts a strategy
decision into a simulated order, applies the configured historical price
rules, and records fills, exits, costs, and position quantities. The
single-pair process handles one active order/position at a time.

## Entry decisions and order types

The strategy uses `StrategySignal::Long`, `Short`, `None`, or `Close`. A long
or short entry must include a positive quantity, stop-loss price, and
take-profit price. With no `limitPrice`, the entry is a market entry; setting a
limit price creates a buy limit for a long signal or a sell limit for a short
signal. There is no general-purpose user-configurable order API exposed in the
Workbench.

Market entries use ask-open for long and bid-open for short, falling back to
general `open` when the quote is missing. A limit entry remains pending until
its candle condition is met:

- buy limit: `ask_low <= limit price`, falling back to general `low`;
- sell limit: `bid_high >= limit price`, falling back to general `high`.

When a limit triggers, the fill uses a favorable open if the relevant ask/bid
open is already better than the limit; otherwise it uses the limit price.
Configured slippage is applied but the final limit price is capped so the
simulated fill does not become worse than the specified limit.

## Lifecycle and fill records

An entry decision is recorded in the decision history. The order history entry
then records the position-level order and its fills. Each fill has a
timestamp, quantity, price, and price source; entry and exit fills are separate
records. The order also retains requested and filled quantities, cancelled
remainders, cost totals, risk amounts, and its exit reason.

`entry_order_status` in decision output can be `pending`,
`partially_filled`, `filled`, or `cancelled`, depending on the limit/partial
fill path. These are entry-status labels, not a complete broker-style order
state machine. A pending limit can be filled on a later candle or cancelled
when a `Close` decision cancels that unfilled pending entry. When a backtest
ends before the limit triggers, the entry remains unfilled.

With synthetic partial fills enabled, each eligible attempt fills up to
`requested quantity × fill ratio`, capped by the remaining quantity. The
default unfilled policy keeps a remainder for later eligible candles;
`cancel` cancels the remainder. For exits, cancellation of an unfilled
remainder does not close the exposure: the still-open quantity waits for a
later exit trigger. Candle volume and order-book depth are not used.

An entry order represents the simulated trade position; it is not a separate
exchange order ledger. Multiple entry/exit fills can belong to the same order.
Partial exit fills reduce the remaining position quantity; the order is
closed when no position quantity remains.

## Exits, long/short prices, and competing conditions

Stop-loss and take-profit conditions are checked against candle ranges. Long
positions use the bid side when available; shorts use the ask side. If both
stop and target are reached in the same candle, the engine chooses the
stop-loss first because OHLC candles do not reveal the intrabar path. A `Close`
signal closes an existing position using that candle's market-open reference
(long: bid open, short: ask open, with general `open` fallback), unless a
stop/target exit already takes precedence on that candle.

When a stop or target is crossed, the exit reference is the configured stop or
target level according to the engine's rules; this is not a tick-by-tick
simulation of price gaps. A target stop may therefore not represent the exact
market execution during a gap. Quote fallback, slippage, and partial fills
also affect the final fill.

Entry decisions generated from a candle are evaluated as the engine walks
that candle sequence. The supplied moving-average strategy uses the candle
close series while the entry can be filled at the same candle's open. This
timing mismatch can create look-ahead bias; it is an important limitation when
interpreting results.

For market-type runs, each pair first generates local order history. A
portfolio synchronization step merges events in timestamp order, processes
exits before entries at the same timestamp, and allocates shared capital
between simultaneous entry requests. Ties are resolved deterministically by
pair/order identifiers. See
[Capital and position sizing](capital-and-position-sizing.md) for the
allocation behavior.

## Insufficient capital, cancellation, and open positions

The single-pair process does not reject a position because its notional value
or requested risk exceeds initial capital. It deducts modeled transaction
costs from capital; it does not reserve or debit position notional as margin.
An unfilled limit can remain pending, and a partial fill can leave an
unfilled remainder according to policy. No automatic end-of-window liquidation
is performed: a position without an exit remains open, with its entry costs
already charged but no mark-to-market unrealized P&L.

The shared-capital market-type synchronizer can scale entry quantities and
risk allocations when simultaneous or competing requests exceed available
risk capital. That is distinct from a single-pair margin check; no broker
margin model is provided.

## Debugging a trade

On the results page, inspect the decision row around the signal and its
`entry_order_status`, then inspect the corresponding order and each
`entry_fills`/`exit_fills` record. Compare the fill's source field with the
pair's bid/ask OHLC values and the configured slippage/partial-fill settings.
Check the order's requested quantity, actual position size, cancelled
quantity, close date, exit reason, and transaction-cost totals.

For a missing entry, check signal direction, valid positive quantity, stop
and target presence, limit trigger prices, and the order-status value. For a
missing exit, check that the order still has remaining quantity, that the
selected candle reached a stop/target or supplied `Close`, and whether partial
exit remainders were kept or cancelled. See
[Fees and execution](fees-and-execution.md) for exact price and cost rules.
