# Limit orders

## Purpose and availability

The implemented limit feature is an entry instruction carried by a strategy
decision. A non-null `StrategyDecision::limitPrice` makes a `Long` decision a
buy limit and a `Short` decision a sell limit. It is not a general UI order
ticket, and there are no independently configurable limit exits.

## Trigger and execution prices

| Entry | Trigger condition | Preferred open for price improvement | General-OHLC fallback |
|---|---|---|---|
| Buy limit | `ask_low <= limitPrice` | `ask_open <= limitPrice` | `low`, then `open` |
| Sell limit | `bid_high >= limitPrice` | `bid_open >= limitPrice` | `high`, then `open` |

If the trigger-side field is non-numeric, the trigger falls back to the
corresponding general low/high. A missing/non-numeric fallback cannot trigger
the order. Once triggered, the engine fills at the better eligible open if
that open is already through the limit; otherwise it uses the limit price.
It records the selected price source (the open field or trigger-side extreme).

Fixed slippage is applied after choosing the reference price, but the final
buy fill is capped at no more than the limit and the final sell fill at no
less than the limit. Thus modeled slippage cannot make a limit execution worse
than its specified limit. The model does not simulate queue priority, hidden
liquidity, or partial execution probabilities.

## Pending lifecycle and partial fills

The decision candle creates a pending state after the engine has processed
existing positions. The order is tested on later candles; an order that never
triggers by the end of the loaded window remains pending in decision history.
It is not an expired or automatically cancelled order. A `Close` decision
cancels the pending entry when that candle does not trigger it. On a trigger,
the order is created and records an entry fill; with partial fills enabled,
each eligible attempt fills up to the configured ratio. `keep` retries the
remaining limit quantity on later qualifying candles; `cancel` records the
unfilled remainder as cancelled.

After a limit fill, the engine skips stop/target evaluation for that candle.
On following candles, the filled position follows the regular stop/target and
close rules. Entry and exit fills, remaining position, and cancelled quantity
are retained on the position-level order record.

## Gaps, ambiguity, and constraints

The better-open rule means a gap through the limit can be assigned the open
price if it is favorable. Otherwise the touched limit price is used. OHLC
cannot disclose intrabar sequence, so a trigger at the low/high is treated as
eligible without knowing how long price traded there or whether sufficient
liquidity existed. Bid/ask fields are preferred, with general low/high/open
fallback; if the general prices are not quote prices, no synthetic spread is
added.

Limit prices must be usable with the entry signal and stop/target constraints;
an entry and stop at the same price is rejected. No separate expiration,
time-in-force, replace, or broker cancellation API exists. An unfilled pending
entry has a decision status but may not appear in the filled order-history
collection. The serialized order `entry_status` field is currently constant
`filled`; inspect decision status and fill/remainder fields for actual
progress.

## Example and verification

An actual decision can express a buy limit as follows:

```php
new StrategyDecision(
    StrategySignal::Long,
    stopLoss: 98.0,
    takeProfit: 106.0,
    quantity: 2.0,
    limitPrice: 100.0,
);
```

If `ask_low` later reaches `100.0` and `ask_open` is `99.5`, the modeled
reference is `99.5` (then slippage, capped at `100.0`). If the open is `100.2`
but the low reaches `100.0`, the reference is the limit `100.0` (then the
limit cap is applied). This is a deterministic OHLC rule, not a promise of a
live fill.

Tests for buy/sell triggers, gaps, and subsequent exits are in
[`SpecificPairBacktestDomainTest`](../../tests/Unit/Engine/Domain/SpecificPairBacktestDomainTest.php).
Related: [order types](order-types.md), [market data](market-support.md),
and [fees and execution](transaction-costs.md).
