# Capital and Position Sizing

## Initial capital and quantity units

The Workbench requires positive initial capital in USD. Price data and all
money amounts are assumed to be normalized to USD; Nikaido does not convert
pair currencies. A pair is configured with one of these strategy quantity
units:

- `units`: quantity is expressed directly in the base units;
- `contract`: quantity is expressed in contracts and contract size converts
  contracts into underlying units; or
- `lot`: quantity is expressed in lots and contract size converts lots to
  position units.

Each pair must have a complete CFD contract profile: contract size, tick size,
tick value in USD per tick per strategy quantity, minimum quantity, quantity
step, and initial margin rate. Existing pairs with incomplete profiles must be
completed manually; the application does not infer broker-specific values.

The strategy, rather than the form, supplies each entry quantity. The engine
rounds it down to the configured quantity step and rejects it if it is below
the minimum. P/L and stop risk use tick value: one price unit is worth
`tick value / tick size` USD for one strategy quantity. The current
moving-average strategy requests risk equal to 1% of its `currentCapital`,
then sizes quantity using that USD P/L per price unit and the distance from its
reference entry to its stop-loss. This is a fixed strategy rule, not a
user-selected risk setting.

Conceptually, for risk amount `R`, entry-to-stop distance `D`, and USD P/L per
price unit `T` for one strategy quantity, the requested quantity is:

`quantity = R / (D × T)`

For orders, position size is normalized to underlying units using contract
size. P/L uses the tick value separately from notional, fees, and margin.
Notional is normalized position size multiplied by USD-normalized entry price.
The order record retains both strategy quantity and normalized position size.

Illustration for a units pair with 10,000 USD of capital, a 0.0001 tick size,
and a USD 1 tick value:

- strategy risk request: `10,000 × 1% = 100`;
- entry reference: `1.1000`; stop: `1.0950`; distance `D = 0.0050`;
- P/L per price unit: `1 / 0.0001 = 10,000 USD`;
- requested quantity: `100 / (0.0050 × 10,000) = 2` units;
- gross loss at the stop, before costs and execution differences:
  `2 × 0.0050 × 10,000 = 100 USD`.

This is a sizing illustration, not a guarantee that the eventual loss is 100.
Gaps, quote-side prices, slippage, partial execution, and fees can make the
result differ.

## Capital deductions and realized results

The single-pair process updates the strategy context's capital as the run
progresses. Configured entry fees are deducted as fills occur; net exit P&L
and exit costs affect capital when exits fill. Overnight financing can also be
charged at exit. An entry is rejected when its initial margin
(`USD notional × initial margin rate`) exceeds available capital. Margin is
not deducted from capital, and the engine does not model maintenance margin,
forced liquidation, or a broker-specific solvency policy. Costs and later
losses can therefore reduce capital below the amount originally required for
margin.

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
- **Initial margin requirement**: a gate on new entries based on USD notional;
  it is not an amount deducted from capital.
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

Shared capital does not convert pair currencies. The implementation combines
the amounts as provided, so only combine pairs whose capital, requested-risk,
notional, and P&L values are normalized compatibly to USD. It reserves both
stop-risk and initial margin while positions are open. Pair decisions are
still generated independently before this synchronized allocation; the
strategy is not rerun with the shared account's current balance. If pair data
has no candle for a period, it contributes no event at that time; missing
candles are not interpolated.

Per-pair concurrent positions are possible in a market-type portfolio, but
the single-pair process permits only one active order/position at a time. For
shared runs, inspect each pair's allocated risk, allocation factor, and
capital-before/after fields along with the top-level run summary.

## Unsupported constraints and edge cases

- There is no UI input for fixed-notional, fixed-quantity, or user-selected
  percentage-risk models; the current strategy's sizing rule is code-level.
- The engine does not reserve margin as a cash deduction, enforce maintenance
  margin or leverage limits, force-liquidate positions, or prevent losses and
  costs from making capital negative.
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
