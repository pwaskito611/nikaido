# Market support

## Product focus and research horizons

Nikaido is a research workbench for intraday and swing trading ideas on CFD
and spot market price histories. Intraday and swing are research horizons, not
separate engine modes or strategy presets: users select the timeframe and
date range for their data, and can configure an entry schedule. The engine
does not resample candles or infer an interval from its label.

The current execution model has important instrument limits. Every pair
requires a CFD contract profile, including tick value, quantity constraints,
and an initial-margin rate. Spot price histories can be explored and tested as
price series, but the simulation does not model buying and owning spot assets,
cash settlement, custody, or asset-specific short availability/borrowing.
Treat spot results as price-based research under the configured profile, not
as a faithful spot-account simulation. No broker connection or live execution
is provided.

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

Each pair requires a positive CFD profile: `contract_size`, `tick_size`,
`tick_value`, `minimum_quantity`, `quantity_step`, and `initial_margin_rate`.
The profile drives quantity rounding, P&L conversion, and initial-margin
checks; it is user supplied and is not validated against a broker or venue.
The simulation does not model maintenance margin, forced liquidation,
short availability/borrow rules, settlement, or contract expiry. All
currencies and monetary values must already use compatible USD
denominations; no FX conversion is applied.

## Timeframes and market prices

A `TimeframeType` is a unique name of up to 50 characters. Its ID groups
candles, but Nikaido does not interpret the name, validate interval spacing,
or resample data. A “Daily” timeframe containing hourly or irregular
timestamps is still processed as stored. Users may select intraday intervals
such as minutes/hours or swing-oriented intervals such as 4h/daily data; these
are data choices, not built-in horizon presets. The configured schedule gates
new entries, while existing positions continue to be monitored on later
candles.

Each `MarketPrice` row is associated with pair, timeframe, and a `price_date`
stored as a timezone-naive date-time. General OHLC values and optional
bid/ask-specific OHLC values use decimal storage with six decimal places.
`price_at_8` is a legacy extra field and is not part of the strategy's OHLC
execution logic. The push-candle endpoint requires general OHLC; the database
allows null values and not every ingestion path has identical validation.

Strategies consume the supplied price series, and execution uses quote-side
values when numeric, otherwise falls back to general OHLC: long entries use
ask-open, short entries bid-open; long exits use bid-side prices, short exits
ask-side. Limit triggers use ask-low for buys and bid-high for sells. No
midpoint or synthetic spread is generated. A missing value may cause the
relevant entry/exit to fail or fall back; there is no general gap-filling
procedure. These rules describe candle simulation, not venue-specific
execution.

## Available input paths and constraints

The Workbench supports manual records and the incoming-candle receiver. The
receiver accepts data sent by an external client, but Nikaido does not fetch
from a provider or transform provider-specific formats. Data availability and
historical depth depend on the configured sender. See
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
currencies or market-specific calendar beyond the user's configured active
schedule. CFD dividend adjustments can be supplied as separate USD cash-flow
events; the engine does not infer them from prices or model spot distributions.
Strategies and pair profiles must use compatible price, quantity, and currency
assumptions when reused across pairs.

## Verification and related documentation

Pair and timeframe validation is implemented in
[`MarketPairRequest`](../../laravel/app/Feature/MarketPairs/Request/MarketPairRequest.php)
and
[`TimeframeTypeRequest`](../../laravel/app/Feature/Timeframes/Request/TimeframeTypeRequest.php);
the quantity-unit enum is
[`MarketPairQuantityUnit`](../../laravel/app/Feature/MarketPairs/Data/MarketPairQuantityUnit.php).
Data and ordering checks are exercised through the
[backtest and import tests](../../laravel/tests/Feature/RunSpecificPairBacktestActionFeatureTest.php).

Related: [market data](../user-guide/market-data.md),
[installation](../getting-started/installation.md),
[backtest engine](backtest-engine.md), and
[multi-pair backtesting](multi-pair-backtesting.md).
