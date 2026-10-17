# Metrics and results

This page documents the result structures exposed by the current single-pair
API, Workbench flow, and persisted market-type runs. It complements
[Performance metrics](../features/performance-metrics.md), which is the
canonical reference for metric formulas and edge cases.
The request and persistence paths that produce these structures are shown in
[Application flows](../architecture/application-flow.md).

## In-memory result

The simulation returns a `SpecificPairBacktestResult` with:

| Property | PHP type | Meaning |
|---|---|---|
| `decisions` | `Vec<BacktestDecision>` | One strategy/execution decision record for each processed candle. |
| `orders` | `Vec<BacktestOrderHistoryEntry>` | Position-level order history, including partial fills. |
| `initialCapital`, `endingCapital` | `float` | Starting and ending realized capital values. |
| `quantityUnit` | `string` | Unit used for strategy quantities. |
| `unitsPerLot`, `quantityMultiplier` | `float` | Pair sizing metadata. |
| `executionConfiguration` | `BacktestExecutionConfiguration` | Slippage, partial-fill, and trade-cost settings. |

The classes are internal typed data, not a separate public HTTP schema.

## Specific-pair JSON API

`POST /api/engine/specific-pair-test` returns Laravel's resource-collection
envelope. `data` is a list of decision records. Each decision contains:

| Field | JSON type | Meaning |
|---|---|---|
| `candle` | object | Serialized `MarketPrice` attributes for the decision's candle. Price columns are decimal-cast values; nullable quote/general price fields may be `null`. |
| `result` | boolean | Whether the decision resulted in an entry. |
| `signal` | string | Strategy signal value (`long`, `short`, `none`, or `close`). |
| `quantity` | number, string, or `null` | Strategy quantity when present. |
| `quantity_unit` | string | `units`, `contract`, or `lot`. |
| `stop_loss`, `take_profit` | number or `null` | Decision price levels. |
| `entry_order_status` | string or `null` | Entry state recorded for the decision. |
| `entry_limit_price` | number or `null` | Requested limit price, if any. |

The JSON object also includes these root-level fields:

| Field | JSON type | Meaning |
|---|---|---|
| `order_history` | array of objects | Serialized position-level orders and their fills. |
| `initial_capital`, `ending_capital` | number | Capital values for the simulation. |
| `quantity_unit` | string | Pair quantity unit. |
| `units_per_lot`, `quantity_multiplier` | number | Pair sizing multipliers. |
| `execution_config` | object | `slippage_enabled`, `slippage_pips`, nullable `slippage_pip_size`, `partial_fill_enabled`, `partial_fill_ratio`, `unfilled_order_policy`, and `trade_costs`. |

Within `execution_config.trade_costs`, `enabled` is a boolean and `components`
contains `commission`, `exchange_fee`, `clearing_fee`, `regulatory_fee`,
`transaction_tax`, `borrow_fee`, and `financing_fee`. Each maps to an object
containing boolean `enabled`, string `method`, numeric `rate`, and string
`applies_to`.

An order-history object includes identity and signal, `open_date` and nullable
`close_date`, `entry_order_type`, entry/exit prices and price sources,
stop/target and close reason, price-extreme observations, risk and quantity
fields, remaining/cancelled/closed quantities, entry/exit fill lists, gross
and net realized P&L, cost totals, capital at open, nullable completed
`profit_loss`, and nullable `capital_after_close`.

**Serialization caveat:** `BacktestOrderHistoryEntry::jsonSerialize()` emits
`entry_status: "filled"` for every order. It is not a reliable indicator of a
pending or partially filled limit entry. Use the decision-level
`entry_order_status`, fill arrays, and remaining/cancelled quantities to
inspect those cases; see [Order types](../features/order-types.md) and
[Limit orders](../features/limit-orders.md).

The API response is a simulation result, not a persisted backtest resource.
It does not include the ordinary analysis metrics listed below.

## Workbench and persisted result shapes

For a synchronous single-pair Workbench run, the controller stores an array in
the Laravel session under `backtestResult`. Its keys are:

`pair`, `timeframe`, `from`, `to`, `initial_capital`, `quantity_unit`,
`units_per_lot`, `quantity_multiplier`, `ending_capital`, `results`,
`order_history`, and `execution_config`.

`results` contains serialized decision records and `order_history` contains
serialized position-level orders. The result is session state, not a durable
backtest-results database row.

Market-type runs are persisted. The parent run stores its input
`configuration`, status/timing, and pair count. Each pair-run row stores status,
timing, an optional JSON `result` snapshot, and an optional failure message.
The JSON result has the same configured-pair keys above. For shared-capital
runs, the combined overall result can add pair and portfolio-allocation data;
see [Shared capital](../features/shared-capital.md) and
[Multi-pair backtesting](../features/multi-pair-backtesting.md).

The Workbench results page computes its analysis when the page is requested;
stress and Monte Carlo outputs are optional analysis results rather than
additional fields in the original single-pair API response. See
[Robustness analysis](../features/robustness-analysis.md).

## Workbench analysis values

The results controller passes these values to its Blade view:

| View value | PHP type | Notes |
|---|---|---|
| `profitFactor` | `?float` | May be positive infinity for gains with no losses in ordinary analysis; empty/zero cases are described in the metrics reference. |
| `closedTradeCount` | `int` | Count of closed position-level trades included in the analysis. |
| `averageDrawdownPercent`, `maximumDrawdownPercent`, `medianDrawdownPercent` | `float` | Realized-event drawdown percentages, not mark-to-market equity drawdowns. |
| `clusterAnalysis` | object/array | `orders`, `closed`, `wins`, `losses`, `net_profit_loss`, `win_rate`, `capital_start`, and `capital_end`. |
| `capitalChart` | object/array | `points` (serialized chart points), `minimum`, `maximum`, `start`, and `end`. |
| `stressTest` | object/array or `null` | Optional fluctuation-stress baseline/scenario output. |
| `monteCarlo` | object/array or `null` | Optional sampled trade-sequence output. |

The view also receives the result array, presented order list, a paginated
decision list, cluster selection, and page-navigation metadata. The decision
list is paginated at 50 rows per page. Stress and Monte Carlo structures are
defined by their corresponding feature pages; this page does not duplicate
their formulas.

## Metric definitions and tests

See [Performance metrics](../features/performance-metrics.md) for formulas,
units, and edge cases. Result construction and analysis are covered by
[`ConfiguredPairBacktestResult`](../../app/Feature/Backtest/Data/ConfiguredPairBacktestResult.php),
[`SpecificPairTestResultResource`](../../app/Feature/Backtest/Resource/SpecificPairTestResultResource.php),
[`BacktestController`](../../app/Feature/Backtest/Controller/BacktestController.php),
and focused tests including
[`SpecificPairTestRequestTest`](../../tests/Feature/SpecificPairTestRequestTest.php).
