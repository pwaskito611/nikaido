# Transaction costs and execution assumptions

## Scope

Nikaido can use quote-side candle prices, fixed adverse slippage, synthetic
partial fills, transaction charges, and holding costs. These are configurable
simulation assumptions; they do not model an order book, liquidity, market
impact, exchange routing, broker fee schedules, or guaranteed live fills.
Operational form instructions are in
[Fees and execution](../user-guide/fees-and-execution.md).

## Price sides, spread, and slippage

Long market entries prefer `ask_open`; short entries prefer `bid_open`. Long
exits use bid-side values and short exits use ask-side values. Missing or
non-numeric quote values fall back to the corresponding general OHLC field.
Limit triggers use ask-low (buy) or bid-high (sell). Generic OHLC is used
without adding a spread or midpoint. A spread is therefore represented only
if the supplied bid/ask prices embody one.

When enabled, fixed slippage adjusts every simulated market/exit reference by
`pips × pipSize`: buys add the amount, sells subtract it. Default pip size is
`0.0001`, or `0.01` for JPY quote pairs; an explicit positive price increment
can override it. It is not volatility-, liquidity-, or size-dependent.
Limit-order execution is capped so slippage cannot breach the limit.

## Configurable costs and formulas

Top-level `trade_costs.enabled` and each component's `enabled` flag must both
be true. Transaction components are commission, exchange fee, clearing fee,
regulatory fee, and transaction tax. Each accepts `per_order`,
`transaction_value_percent`, or `per_quantity` and can apply to `entry`,
`exit`, or `both`.

For executed quantity `q`, execution price `p`, configured P&L multiplier
`m`, percentage rate `r`, and per-quantity rate `u`:

| Method | Cost |
|---|---|
| `per_order` | `r`, once for the configured side of the position order |
| `transaction_value_percent` | `(q × p × m) × r / 100` |
| `per_quantity` | `quantityInConfiguredUnit × u` |

For lots, `quantityInConfiguredUnit` converts normalized position quantity
back to lots; contract fees use contract quantity. Percentage transaction
value uses normalized quantity and the P&L multiplier. Partial fills charge
percentage and per-quantity costs on the executed quantity. A `per_order`
component is charged once per entry side and once per exit side, not once per
partial-fill attempt.

Borrow fee and financing fee accept only `daily_notional_percent`. For each
closed portion, notional-days are summed from entry fills as
`filled quantity × entry fill price × P&L multiplier × elapsed seconds / 86400`.
The holding charge is `notionalDays × rate / 100`. Financing can apply to
either direction; borrow fee is charged only for short positions. The cost is
calculated at exit and charged against that exit fill. The `applies_to` value
is accepted on holding components but the current calculation does not use it;
it does not alter when holding fees are applied.

## Worked calculation

Illustrative units position: buy 10 units at 100 and close at 110 one day
later. Configure 1% transaction-value commission on both sides, a 2-unit
per-order exchange fee on both sides, and 1% daily-notional financing. With
multiplier 1:

- Gross exit P&L: `(110 - 100) × 10 = 100`.
- Entry and exit commissions: `1,000 × 1% + 1,100 × 1% = 21`.
- Entry and exit exchange fees: `2 + 2 = 4`.
- Financing: `10 × 100 × 1 day × 1% = 10`.
- Net closed-trade P&L: `100 - 21 - 4 - 10 = 65`.

Entry fees reduce capital when entry fills occur. Exit P&L is net of exit and
holding costs and updates capital when the exit fill is recorded. Repeated
partial fills use numeric decimal arithmetic through the project's number
library, but there is no currency-minor-unit or exchange tick rounding.

## Validation, defaults, and limitations

Costs and slippage are disabled by default. Rates must be finite and
non-negative; `per_order`, percentage, and per-quantity methods are restricted
to transaction components. Holding components accept only
`daily_notional_percent`. Fill ratio must be in `(0, 1]`; `keep` retries an
unfilled remainder and `cancel` cancels it (an unfilled exit remainder leaves
the position open). Entry price gaps, candle volume, minimum fees, fee caps,
and instrument-specific rounding are not modeled.

Rate inputs are percentages: `0.1` means 0.1%, not 10%. There is no automatic
conversion of fee currency or per-quantity rate units. Document the fee
assumptions and data price side for every research comparison.

## Verification and related documentation

Cost formulas and timing are exercised in
[`SpecificPairBacktestDomainTest`](../../tests/Unit/Engine/Domain/SpecificPairBacktestDomainTest.php).
Related: [fees and execution](../user-guide/fees-and-execution.md),
[shared capital](shared-capital.md), and [backtest engine](backtest-engine.md).
