# Market support

## Purpose and scope

Nikaido stores named market groups, pairs, timeframes, and price candles so
they can be selected by backtests. Those records provide identifiers and
quantity conventions; they do not by themselves implement the mechanics of a
particular exchange, asset class, or broker.

## Market and instrument representation

`MarketType` contains a unique display name (maximum 50 characters). There is
no built-in market-category enum, market-specific order policy, exchange
calendar, or contract specification attached to the type. A user can name a
type “FX”, “Equities”, or anything else, but that label does not enable
asset-specific execution rules.

A market pair belongs to one market type and has `base` and `quote` text
identifiers (up to 10 characters each). Pair uniqueness is scoped to market
type plus base/quote in the Workbench. No currency conversion is performed.
The stored `quantity_unit` is one of:

| Unit | Engine interpretation |
|---|---|
| `units` | Strategy quantity is the position quantity; P&L multiplier is 1. |
| `contract` | Quantity is contracts; configured `contract_size` is used as the P&L multiplier. |
| `lot` | Strategy quantity is lots; configured contract size converts it to position quantity. |

For contract and lot pairs, the Workbench requires a positive `contract_size`
with up to eight decimal places. The field is a generic multiplier, not a
complete instrument specification. Tick sizes, lot steps, margin, leverage,
short availability, settlement, and contract expiry are not simulated.

## Timeframes and market prices

A `TimeframeType` is a unique name of up to 50 characters. Its ID groups
candles, but Nikaido does not interpret the name, validate interval spacing,
or resample data. A “Daily” timeframe containing hourly or irregular
timestamps is still processed as stored.

Each `MarketPrice` row is associated with pair, timeframe, and a `price_date`
stored as a timezone-naive date-time. General OHLC values and optional
bid/ask-specific OHLC values use decimal storage with six decimal places.
`price_at_8` is a legacy extra field and is not part of the strategy's OHLC
execution logic. The push-candle endpoint requires general OHLC; the database
allows null values and not every ingestion path has identical validation.

The strategy uses general `close` values for its moving averages. Execution
uses quote-side values when numeric, otherwise falls back to general OHLC:
long entries use ask-open, short entries bid-open; long exits use bid-side
prices, short exits ask-side. Limit triggers use ask-low for buys and bid-high
for sells. No midpoint or synthetic spread is generated. A missing value may
cause the relevant entry/exit to fail or fall back; there is no general
gap-filling procedure.

## Available input paths and constraints

The Workbench supports manual records, the implemented Alpha Vantage FX
import flow, and the incoming-candle receiver. The external provider
integration is specifically an FX request path; it does not imply support for
other vendors, file formats, exchanges, or asset classes. Provider availability
and historical depth are external constraints. See
[market data](../user-guide/market-data.md) for ingestion usage and validation
differences.

Market-pair requests normalize base/quote to uppercase and trim whitespace;
these identifiers are not validated against a global currency or ticker
catalog. Timeframe labels are similarly user-defined. The market-type
backtest selects pair rows in one group that have at least one matching
timeframe candle in the requested interval. Unsupported quantity-unit values
in stored data are rejected by the repository rather than silently interpreted.

All prices, capital, risk, fees, and P&L are combined numerically in the run's
assumed account denomination. There is no FX conversion between quote
currencies, no dividend or corporate-action adjustment, and no market-specific
calendar or trading-hours policy beyond the user's configured active schedule.
Strategies must therefore use compatible price and quantity assumptions when
reused across pairs.

## Verification and related documentation

Pair and timeframe validation is implemented in
[`MarketPairRequest`](../../app/Feature/MarketPairs/Request/MarketPairRequest.php)
and
[`TimeframeTypeRequest`](../../app/Feature/Timeframes/Request/TimeframeTypeRequest.php);
the quantity-unit enum is
[`MarketPairQuantityUnit`](../../app/Feature/MarketPairs/Data/MarketPairQuantityUnit.php).
Data and ordering checks are exercised through the
[backtest and import tests](../../tests/Feature/RunSpecificPairBacktestActionFeatureTest.php).

Related: [market data](../user-guide/market-data.md),
[installation](../getting-started/installation.md),
[backtest engine](backtest-engine.md), and
[multi-pair backtesting](multi-pair-backtesting.md).
