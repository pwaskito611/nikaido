# Capital and Position Sizing

## Initial capital and quantity units

The Workbench requires positive initial capital for each run. For a
single-pair run it represents the account's capital in the pair's quote
currency; Nikaido does not convert currencies. A pair is configured with one
of these quantity units:

- `units`: quantity is expressed directly in the base units;
- `contract`: quantity is expressed in contracts and the configured contract
  size is used as a P&L multiplier; or
- `lot`: quantity is expressed in lots and contract size converts lots to
  position units.

The strategy, rather than the form, supplies each entry quantity. The current
moving-average strategy requests risk equal to 1% of its `currentCapital`,
then sizes quantity using the distance from its reference entry to its
stop-loss. This is a fixed strategy rule, not a user-selected risk setting.

Conceptually, for risk amount `R`, entry-to-stop distance `D`, and the
instrument's P&L multiplier `M`, the requested quantity is:

`quantity = R / (D × M)`

For units, `M` is 1. For contracts/lots, the strategy's quantity multiplier
accounts for the configured instrument size; the order record separately
retains the strategy quantity, normalized position size, and P&L multiplier.
The order's requested risk is derived from its entry-to-stop distance and
normalized quantity.

Illustration for a units pair with 10,000 account units of capital:

- strategy risk request: `10,000 × 1% = 100`;
- entry reference: `1.1000`; stop: `1.0950`; distance `D = 0.0050`;
- requested quantity: `100 / 0.0050 = 20,000` units;
- gross loss at the stop, before costs and execution differences:
  `20,000 × 0.0050 = 100`.

This is a sizing illustration, not a guarantee that the eventual loss is 100.
Gaps, quote-side prices, slippage, partial execution, and fees can make the
result differ.

## Capital deductions and realized results

The single-pair process updates the strategy context's capital as the run
progresses. Configured entry fees are deducted as fills occur; net exit P&L
and exit costs affect capital when exits fill. Holding fees can also be
charged at exit. Position notional is not deducted as a cash or margin
reservation, and there is no general insufficient-margin rejection rule.
Capital can therefore be reduced by costs without the engine enforcing a
broker-like liquidation or solvency policy.

The engine records trade P&L when a position is closed. It does not mark open
positions to market at each candle or force them closed at the end of the
window. Ending capital includes capital changes applied during the simulation
(including entry costs), but it does not represent equity including the
unrealized value of any position still open. The results' capital/drawdown
series is likewise based on recorded realized exit events, not a full
intrabar equity curve.

Do not treat these distinct quantities as interchangeable:

- **Initial capital**: configured starting amount.
- **Current/ending capital**: the simulation's capital value after applied
  costs and realized fills.
- **Position size**: quantity opened, not cash invested or margin reserved.
- **Requested risk**: the strategy's stop-distance-based sizing request,
  before actual fill and execution differences.
- **Equity**: a live-style mark-to-market value is not calculated for open
  positions by this engine.

## Multiple pairs and shared capital

A market-type run can use independent starting capital per pair or shared
capital. In shared mode, separate pair jobs generate their histories and a
portfolio synchronization process merges events by their actual timestamps.
It processes exits before entries at the same timestamp, then allocates
eligible entry risk. When requests at one timestamp compete for insufficient
shared risk capital, allocation is scaled proportionally; resulting quantities
and costs are adjusted by that allocation factor. This preserves the event
time differences between pairs, including when their historical series begin
on different dates.

Shared capital does not convert pair quote currencies. The implementation
combines the amounts as provided, so only combine pairs whose capital,
requested-risk, and P&L values are denominated compatibly. If pair data has no
candle for a period, it contributes no event at that time; missing candles
are not interpolated.

Per-pair concurrent positions are possible in a market-type portfolio, but
the single-pair process permits only one active order/position at a time. For
shared runs, inspect each pair's allocated risk, allocation factor, and
capital-before/after fields along with the top-level run summary.

## Unsupported constraints and edge cases

- There is no UI input for fixed-notional, fixed-quantity, or user-selected
  percentage-risk models; the current strategy's sizing rule is code-level.
- The single-pair runner does not reserve notional, enforce leverage or margin,
  cap quantity by capital, or prevent total costs from making capital
  negative.
- No FX conversion is applied across quote currencies.
- The supplied strategy's risk amount is computed from its stop distance; a
  stop equal to the reference entry cannot size an order and invalid entry
  values raise an error.
- Costs can make realized net P&L differ from the stop-risk request. Partial
  fills can also mean that the executed position is smaller than the request.
- A multi-pair risk allocation is a deterministic historical simulation, not
  a guarantee about real-time order arrival or broker allocation.

For the complete fee and quote-side rules, see
[Fees and execution](fees-and-execution.md). For entry/exit order lifecycle,
see [Orders and positions](orders-and-positions.md).
