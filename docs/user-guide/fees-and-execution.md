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

## Fixed slippage

Slippage is optional and disabled by default. When enabled, it applies a fixed
adverse number of pips: buys add the price adjustment and sells subtract it.
The default FX pip size is `0.0001`, or `0.01` when the quote currency is JPY;
an optional positive pip-size override supports other increments. Slippage
does not model volatility, liquidity, market impact, or order size.

Example: at a long entry ask-open of `1.10000`, 1 pip at `0.0001` changes the
execution reference to `1.10010`. At a long exit, the sell-side adjustment
subtracts a pip from the chosen bid-side reference. A limit order remains
bounded by its limit price even after the slippage adjustment.

Spread and slippage are separate: quote-side prices can already embody a
spread; slippage is applied to the selected execution reference. Do not add a
second spread cost unless it represents a separate, intentionally modeled
charge.

## Additional transaction and holding costs

Additional costs are disabled by default and applied only when enabled and
configured. Transaction cost components are commission, exchange fee,
clearing fee, regulatory fee, and transaction tax. Each supports:

- `per_order`: a fixed amount charged once per order side (entry or exit);
- `transaction_value_percent`: a percentage of executed transaction value;
- `per_quantity`: an amount per quantity unit/contract/lot.

These transaction components can apply to `entry`, `exit`, or `both`. A
percentage rate is entered as a percent number: `0.1` means 0.1%, so a
transaction value of 10,000 incurs `10,000 × 0.1 / 100 = 10`. A `per_order`
amount is charged once on each configured side, not once for every partial
fill. There is no automatic minimum fee.

Borrow fee and financing fee are holding-cost components and use
`daily_notional_percent`. The notional-days calculation uses the filled
quantity and the time it remains open; short positions can accrue borrow fees,
and financing can apply to either direction. Rates are explicit configuration
inputs; no market-specific convention is inferred.

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

The form accepts the same component choices and displays rate inputs. A
transaction value percentage is applied to executed transaction value; for
contracts this includes the configured P&L multiplier. For lots, per-quantity
fees use lot quantity, while transaction value uses normalized position size.
Partial fills incur proportional transaction-value and per-quantity costs on
the executed quantity; per-order charges are not duplicated for each
partial-fill attempt.

## Partial fills and cost timing

Synthetic partial fill behavior is optional and disabled by default. When
enabled, the fill ratio is in `(0, 1]`. Each eligible entry/exit attempt fills
up to `requested quantity × ratio`, capped at the remaining amount. `Keep`
retries a remainder on later eligible candles; `Cancel` cancels that remainder
(for a cancelled exit, the remaining position remains exposed). This is a
quantity rule only. Candle volume and order-book liquidity are not read.

Transaction costs are recorded against fills and deducted from the simulated
capital/P&L. Entry costs are deducted when entry fills occur. Exit costs and
holding fees are charged when the exit fills are processed. Thus gross price
movement and net realized P&L differ, and the running capital can include
entry fees before an order closes.

## Precision and limitations

Stored price columns use six fractional decimal places. Internal calculations
use numeric arithmetic where implemented, but the result is ultimately
represented in the application’s numeric fields. The engine does not round
fees to a broker's currency minor unit, apply tick-size/lot-step rules, or
infer market-specific rounding. Do not equate extra internal precision with a
real exchange's fee-rounding policy.

Validate that:

- percentage rates are entered as percentages, not fractions (`0.1` means
  `0.1%`, not `10%`);
- `per_order` amounts are in the same account currency as the run;
- `per_quantity` rates match the pair's configured unit;
- slippage pip size is a price increment, not a pip count;
- quote-side fields use consistent units and timestamps; and
- your assumed rates and sides are documented for reproducibility.

For sizing and how fees affect capital, see
[Capital and position sizing](capital-and-position-sizing.md). The complete
fill and limit-order rules are in [Orders and positions](orders-and-positions.md).
