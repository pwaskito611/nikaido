# Order and execution semantics

Nikaido turns strategy decisions into simulated candle-based orders. The
recorded order is a position-level history, not a broker order ledger: it
groups one entry intent with any entry fills, exit fills, costs, and remaining
position quantity.

This page summarizes the model. User procedures are in
[Orders and positions](../user-guide/orders-and-positions.md); detailed
implementation rules and examples are in [Order types](../features/order-types.md),
[Limit orders](../features/limit-orders.md), and
[Transaction costs](../features/transaction-costs.md).

## Signals, orders, and positions

The current strategy contract uses four signals:

- `Long` and `Short` request an entry with positive quantity, stop-loss, and
  take-profit prices. Without a limit price they create a market entry; with
  one they create a buy limit or sell limit respectively.
- `None` requests no action.
- `Close` requests an exit from the current position; it does not open a
  position in the opposite direction.

The single-pair process allows only one active order/position or pending
entry at a time. It does not add to or reverse an active position on another
entry signal. A position can contain multiple entry fills and partial exit
fills. It is closed only when its remaining position quantity reaches zero.
There is no automatic liquidation at the end of the selected window.

Entry decisions can be marked `pending`, `partially_filled`, `filled`, or
`cancelled` in decision history. A pending limit that never triggers remains
pending at the end of available data; it is not automatically expired. A
`Close` decision cancels an untriggered pending limit if that candle does not
trigger it. The serialized order record currently emits `entry_status:
"filled"` as a constant, so use the decision status and fill/remainder
quantities to understand actual progress.

## Price selection and triggers

The engine uses quote-side prices when numeric and otherwise falls back to
the corresponding general OHLC field. It does not synthesize a spread.

| Operation | Preferred quote and condition | General-price fallback |
|---|---|---|
| Long market entry | `ask_open` | `open` |
| Short market entry | `bid_open` | `open` |
| Long stop/target test | `bid_low` / `bid_high` | `low` / `high` |
| Short stop/target test | `ask_high` / `ask_low` | `high` / `low` |
| Buy limit trigger | `ask_low <= limit` | `low <= limit` |
| Sell limit trigger | `bid_high >= limit` | `high >= limit` |
| Long `Close` | `bid_open` | `open` |
| Short `Close` | `ask_open` | `open` |

When a limit triggers, a favorable side-specific open through the limit is
used for price improvement; otherwise the limit is the reference price.
Slippage is applied, but the final limit fill remains no worse than the
specified limit.

Stop-loss and take-profit are exit conditions attached to the position, not
independent stop-entry or limit-exit order types. When a threshold is reached,
the engine uses the stop or target level as the exit reference, then applies
configured adverse slippage. The `Close` instruction uses the market-open
reference. The configured entry schedule gates strategy decisions, but
existing positions continue to be checked for price exits outside active
hours/days.

## Intrabar ambiguity, gaps, and fills

OHLC bars do not reveal whether the high or low occurred first. If both stop
and target conditions are met in one candle, stop-loss has precedence. This
is a fixed adverse tie rule, not a recovered intrabar path. Newly opened
market entries are not checked against their stop/target using the same
candle's range in that pass. A newly triggered limit entry also skips
stop/target evaluation for its trigger candle.

The level-based stop/target fill does not model a gap-through fill at the
next available market price. A close at a stop or target can therefore
differ from how an actual order would execute during a gap. A market entry
may be evaluated from a signal that used the same candle's close but filled
at its open; this is a look-ahead risk in the current strategy timing.

Fixed slippage is a configured adverse price adjustment, not a liquidity or
volatility model. Partial fills, when enabled, fill at most the configured
fraction of requested quantity per eligible attempt, capped by the
remaining amount. `keep` retries an unfilled remainder on later eligible
candles; `cancel` cancels that remainder. A cancelled exit remainder leaves
the remaining position open. Candle volume, queue priority, order-book depth,
broker acceptance, and market impact are not modeled.

There is no general-purpose Workbench order ticket, independent stop-order
API, broker order state machine, expiry/time-in-force, or exchange rejection
workflow. Shared-capital allocation can reject a portfolio entry when its
available risk allocation is zero; this is a portfolio simulation outcome,
not a broker rejection.

For detailed transaction and holding-cost timing, refer to
[Fees and execution](../user-guide/fees-and-execution.md). For candle
semantics and data coverage, refer to [Market data](../user-guide/market-data.md).
The broader request and result lifecycle is diagrammed in
[Application flows](../architecture/application-flow.md).
