# Fees and Execution

These settings describe configurable historical assumptions. They do not
simulate a broker, order book, queue position, exchange routing, or guaranteed
live execution. All additional costs are user supplied; Nikaido does not infer
broker schedules, jurisdictional taxes, or minimum charges.

## Price sides and spread

The engine selects prices from stored candle fields:

| Operation | Preferred price | Fallback |
|---|---|---|
| Long market entry | `ask_open` | `open` |
| Short market entry | `bid_open` | `open` |
| Long exit | bid-side close/stop/target fields | corresponding general OHLC field |
| Short exit | ask-side close/stop/target fields | corresponding general OHLC field |
| Buy-limit trigger | `ask_low` | `low` |
| Sell-limit trigger | `bid_high` | `high` |

The preferred quote fields are used only when numeric. General prices are
used as-is when quote data is absent; Nikaido does not invent a spread or
midpoint. If general OHLC values are already midpoint prices, the fallback
does not add a spread. Supply consistent bid/ask history when the research
question depends on the spread.

## Slippage models

Slippage is optional and disabled by default. Each model expresses its inputs
in pips. The default FX pip size is `0.0001`, or `0.01` when the quote
currency is JPY; an optional positive pip-size override supports other
increments. Slippage is calculated for each actual fill, so unfilled quantity
does not incur a price adjustment. Direction is configured separately from
magnitude: **adverse only** (the backward-compatible default) always worsens
the execution price for the trade side; **random** chooses adverse or favorable
direction with equal probability on each fill. Direction uses the configured
seed as part of the same isolated random sequence as random magnitude.

- **Fixed:** applies the configured constant adverse pip amount to every fill.
  Existing `slippage_pips` configurations remain aliases for this setting.
- **Dynamic:** adds a base amount, candle high-low range (converted to pips)
  multiplied by a volatility factor, a volume-impact factor multiplied by
  `sqrt(fill quantity / traded volume)`, and a spread factor multiplied by the
  quote-side bid-ask spread converted to pips. The total is capped by maximum
  slippage. Volume impact requires a positive volume explicitly marked as
  `traded`; tick volume, missing volume, and zero volume are not treated as
  traded liquidity. Volatility uses the candle range as a proxy, not a
  statistical volatility estimate. Configure factors as zero when the
  corresponding market data is not available. The volume quantity and
  normalized fill quantity are assumed to use compatible units; current market
  data has no instrument-specific volume-unit metadata.
- **Random:** draws a value from a uniform or normal distribution, bounded by
  the configured minimum and maximum. Normal samples outside those limits are
  clipped to the nearest bound; this is not a mathematically truncated normal
  distribution. An optional seed reproduces the sequence for the same
  execution order.

Example: at a long entry ask-open of `1.10000`, 1 pip at `0.0001` changes the
execution reference to `1.10010`. At a long exit, the sell-side adjustment
subtracts a pip from the chosen bid-side reference. A limit order remains
bounded by its limit price even after the slippage adjustment.

Spread and slippage are separate: quote-side prices can already embody a
spread; slippage is applied to the selected execution reference. Do not add a
second spread cost. Dynamic spread impact uses the supplied bid/ask quotes as
an input to the slippage estimate; it does not add the spread to the execution
reference a second time. No instrument tick-size metadata is currently
available, so prices are adjusted by the configured pip increment without
additional tick rounding.

## Additional transaction and holding costs

Commission and overnight financing are the primary user-configurable CFD
costs and are disabled by default. Advanced transaction components are
exchange fee, clearing fee, regulatory fee, and transaction tax. Each
transaction component supports:

- `per_order`: a fixed amount charged once per order side (entry or exit);
- `transaction_value_percent`: a percentage of executed transaction value;
- `per_quantity`: an amount per quantity unit/contract/lot.

These transaction components can apply to `entry`, `exit`, or `both`. A
percentage rate is entered as a percent number: `0.1` means 0.1%, so a
transaction value of 10,000 incurs `10,000 × 0.1 / 100 = 10`. A `per_order`
amount is charged once on each configured side, not once for every partial
fill. There is no automatic minimum fee.

Overnight financing uses `daily_notional_percent`, based on normalized USD
notional and elapsed 24-hour periods. It is charged on exit and can apply to
long or short positions. A legacy short-borrow fee remains available through
the API/configuration, but cannot be enabled together with financing, preventing
duplicate holding charges. Rates are explicit inputs; no market-specific
convention is inferred.

Example transaction cost configuration, as accepted by
`TradeCostConfig::fromArray`:

```php
use App\Feature\Backtest\Config\TradeCostConfig;

$costs = TradeCostConfig::fromArray([
    'enabled' => true,
    'commission' => [
        'enabled' => true,
        'method' => 'transaction_value_percent',
        'rate' => 0.1,
        'applies_to' => 'both',
    ],
    'transaction_tax' => [
        'enabled' => true,
        'method' => 'per_order',
        'rate' => 2.50,
        'applies_to' => 'exit',
    ],
    'financing_fee' => [
        'enabled' => true,
        'method' => 'daily_notional_percent',
        'rate' => 0.01,
        'applies_to' => 'both',
    ],
]);
```

The form exposes commission and overnight financing first and groups other
transaction fees under advanced settings. A transaction-value percentage is
applied to normalized position quantity multiplied by USD-normalized execution
price. Per-quantity fees use strategy quantity in its configured unit; this is
independent of the tick-value P/L conversion.
Partial fills incur proportional transaction-value and per-quantity costs on
the executed quantity; per-order charges are not duplicated for each
partial-fill attempt. The entry quantity is rounded down to the configured
quantity step and rejected if it is below the instrument's minimum quantity.

## Partial fills and cost timing

Partial fills are optional and disabled by default. The fixed mode preserves
the existing rule: each eligible entry/exit attempt fills up to
`requested quantity × fill ratio`, capped at the remaining amount. The ratio
is in `(0, 1]`.

The volume mode uses only a candle's `volume` when `volume_type` is explicitly
`traded`. Its per-candle execution budget is
`traded volume × max participation rate`; entry and exit fills on that candle
share the same budget. Each fill is capped by the requested and remaining
quantity. The rate is a decimal in `[0, 1]` (for example, `0.10` is 10%). The
supported period is one candle at the selected backtest timeframe. `Keep`
retains and retries the unfilled quantity on later eligible candles; `Cancel`
records the remainder as cancelled (a cancelled exit leaves its remaining
position open).

Missing volume, or a volume value with no explicit type, provides zero
execution capacity. Negative or invalid volume is rejected. Tick volume is
not traded quantity and is never substituted: a candle explicitly marked
`tick` causes volume-mode execution to fail with an explanation. The volume
must use the engine's execution quantity units; lot quantities are normalized
using the pair's configured contract size. Volume must be supplied as
explicitly marked traded volume by the data sender. The model still does not
simulate order-book depth, queue priority, or market impact.

Transaction costs are recorded against fills and deducted from the simulated
capital/P&L. Entry costs are deducted when entry fills occur. Exit costs and
holding fees are charged when the exit fills are processed. Thus gross price
movement and net realized P&L differ, and the running capital can include
entry fees before an order closes.

## Precision and limitations

Stored price columns use six fractional decimal places. Internal calculations
use numeric arithmetic where implemented, but the result is ultimately
represented in the application’s numeric fields. The engine applies the
configured quantity step and minimum; it does not round fees to a broker's
currency minor unit or infer market-specific price rounding. Do not equate
extra internal precision with a real exchange's fee-rounding policy.

Validate that:

- percentage rates are entered as percentages, not fractions (`0.1` means
  `0.1%`, not `10%`);
- `per_order` amounts are in USD;
- `per_quantity` rates match the pair's configured unit;
- slippage pip size is a price increment, not a pip count;
- quote-side fields use consistent units and timestamps; and
- your assumed rates and sides are documented for reproducibility.

For sizing and how fees affect capital, see
[Capital and position sizing](capital-and-position-sizing.md). The complete
fill and limit-order rules are in [Orders and positions](orders-and-positions.md).
