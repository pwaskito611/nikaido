# Order types and exit instructions

## Scope

The strategy decision contract supports market entries, limit entries, and a
separate `Close` exit instruction. Stop-loss and take-profit are price
conditions attached to an entry position; they are not independently
submitted stop-entry or limit-exit order types. Nikaido does not expose a
general broker-style order API in the Workbench.

The signal enum is `Long`, `Short`, `None`, or `Close`. A `Long`/`Short`
decision must provide positive quantity, stop-loss, and take-profit. When
`limitPrice` is null, the engine opens a market entry; when present it creates
a buy limit for `Long` or sell limit for `Short`. `None` does nothing.
`Close` requests exit of an existing position and does not itself open a short
position.

## Market entries

At a market entry, a long uses numeric `ask_open`, falling back to general
`open`; a short uses `bid_open`, with the same fallback. If neither selected
price is numeric, execution fails. Configured fixed slippage is adverse:
buy prices increase and sell prices decrease by
`pips × pipSize`. With no explicit pip size, the engine uses `0.0001`, or
`0.01` when the pair quote is JPY.

The order's risk request is derived from the reference entry/stop distance,
normalized position size, and P&L multiplier. Entry and stop cannot be equal.
The engine does not reserve the entry notional or reject it for insufficient
cash in a single-pair run.

## Stop-loss and take-profit exits

Long positions check bid-low against stop-loss and bid-high against target;
short positions check ask-high against stop-loss and ask-low against target.
When the side-specific value is absent or non-numeric, general low/high is
used. If both thresholds are touched in one candle, stop-loss wins because
OHLC does not show which came first.

The selected stop or target level is the exit reference, including when a
candle gaps past it. Slippage is then applied. This is not a tick-by-tick
simulation, and the fill need not equal a live fill during a gap. A position
continues to be monitored outside active entry hours and excluded weekdays.

## Close instruction and order lifecycle

A `Close` signal is processed only on an active-schedule candle. If no
stop/target exit has taken priority, it closes at the position's market-open
reference: long at bid-open, short at ask-open, with general open fallback.
An active `Close` also cancels an unfilled pending limit when the limit did not
trigger on that candle. There is no automatic close at the end of the data
window.

For active entry decisions, `entry_order_status` in the decision history may
be `pending`, `partially_filled`, `filled`, or `cancelled`. The order record
tracks entry/exit fills, requested and remaining quantities, cancelled
remainders, cost totals, and close reason. There is an output caveat:
`BacktestOrderHistoryEntry::jsonSerialize()` currently emits
`entry_status: "filled"` as a constant. For partially filled or pending
states, rely on decision status, fill lists, and remaining/cancelled quantity
fields rather than that serialized label.

The order object represents one position-level trade and can hold multiple
entry and exit fills. With synthetic partial fills, the fill quantity per
attempt is no more than `requestedQuantity × fillRatio`, capped by what
remains. `keep` retries unfilled quantity on later eligible candles;
`cancel` cancels the remainder. Cancelling an exit remainder leaves the
remaining position open. Candle volume, order-book depth, exchange queue
position, and broker acceptance are not modeled.

## Competing operations and edge cases

Only one active order/position or pending entry can exist per single-pair
run. A new signal while an order is active is not used to reverse or add to
that position. Price exits are checked before a `Close` instruction. Newly
opened market positions are not stop/target-tested against the same candle
that created them. A pending limit starts testing subsequent candles, remains
pending indefinitely while data continues, and is not written as a filled
order unless it triggers.

The strategy uses candle closes to create decisions, although a market entry
can use that candle's open; account for this look-ahead risk when evaluating
results. Exact entry limit behavior is covered in
[limit orders](limit-orders.md).

## Verification and related documentation

Order lifecycle and edge cases are covered in
[`SpecificPairBacktestDomainTest`](../../tests/Unit/Engine/Domain/SpecificPairBacktestDomainTest.php).
See also [orders and positions](../user-guide/orders-and-positions.md),
[fees and execution](../user-guide/fees-and-execution.md), and
[backtest engine](backtest-engine.md).
