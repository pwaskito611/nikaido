# First Backtest

This walkthrough runs a reproducible **synthetic smoke test** through the
Workbench. It generates a flat, 230-candle history in a disposable local
database. The included strategy deliberately produces no trades for constant
prices, so the expected result verifies the data-to-results path without
inventing a performance claim. Do not treat synthetic data as strategy
research.

For a meaningful historical evaluation, replace the generated data with a
quality-checked dataset imported from your chosen source; no sample market data
or default strategy result is shipped with Nikaido.

## Prerequisites

- Complete [Installation](installation.md) and start the local application.
- Use an empty local SQLite database, or back up and switch to a separate
  disposable development database first. Do not run the sample-data insertion
  in a database containing research records.
- Keep the web server available. The single-pair workflow runs in the request;
  a background worker is only needed for queued market-type runs.

## How the workflow works

The Workbench submits a specific pair, timeframe, date range, capital, and
execution settings to the backtest action. The action loads ordered market
candles and up to 200 earlier candles for strategy warm-up. The injected
`Strategy` currently wraps the example 20/50 EMA crossover with a 200-period
trend filter. It returns one decision per candle. The process evaluates those
decisions against the candle data, manages orders and positions, and returns
trade and capital history for the result page.

There is no strategy selector in the current UI. The moving-average strategy is
the strategy currently wired into the engine. A single-pair result is kept in
the browser session; market-type runs use a queue and persist run records.

## Create deterministic smoke-test data

The default database seeder is empty. The following uses the installed Laravel
Tinker console and existing Eloquent models to create one market type, pair,
timeframe, and 230 daily candles. It is intentionally explicit so the smoke
test can be repeated on a fresh, disposable database.

From the project root, start Tinker:

```sh
php artisan tinker
```

Paste the following into the Tinker prompt **once**:

```php
$marketType = \App\Feature\MarketTypes\Model\MarketType::create([
    'name' => 'Documentation smoke test',
]);

$pair = \App\Feature\MarketPairs\Model\MarketPair::create([
    'market_type_id' => $marketType->id,
    'base' => 'DEMO',
    'quote' => 'USD',
    'quantity_unit' => 'units',
    'contract_size' => '1',
]);

$timeframe = \App\Feature\Timeframes\Model\TimeframeType::create([
    'name' => 'Daily',
]);

$firstDay = \Carbon\CarbonImmutable::parse('2020-01-01 12:00:00');
for ($day = 0; $day < 230; $day++) {
    \App\Feature\MarketPrice\Model\MarketPrice::create([
        'pair_id' => $pair->id,
        'timeframe_type_id' => $timeframe->id,
        'price_date' => $firstDay->addDays($day)->format('Y-m-d H:i:s'),
        'open' => '1.100000',
        'high' => '1.100000',
        'low' => '1.100000',
        'close' => '1.100000',
    ]);
}

echo "Pair ID: {$pair->id}; timeframe ID: {$timeframe->id}; candles: 230\n";
```

Exit Tinker with `exit`. The IDs are displayed for confirmation; the Workbench
will show the created pair and timeframe by name. All candles have a unique
timestamp, use valid six-decimal OHLC values, and are stored in increasing
date order. Bid/ask OHLC values are absent, so execution uses the general OHLC
fallback.

## Run the backtest

1. Open `http://127.0.0.1:8000/workbench/tests/create`.
2. Select **Specific pair test**, then choose `DEMO/USD` and `Daily`.
3. Set initial capital to `10000.00`.
4. Set the start to `2020-07-19 00:00:00` and the end to
   `2020-07-29 23:59:59`. These dates fall within the generated records and
   follow 200 earlier daily candles.
5. Select **Run all day**. Leave slippage, partial fills, and additional trade
   costs disabled for this baseline.
6. Submit **Run backtest**. The browser should redirect to the backtest results
   page.

The selected range contains 11 daily candles. With flat prices, the moving
averages do not cross and the trend filter does not generate an entry. Expect
the results page to report the run's evaluated candles and zero opened/closed
orders; no profitable or losing trade metrics should be inferred from this
smoke test. The exact displayed result is the useful check: the pair/timeframe
were loaded, the selected window was processed, and result analysis rendered.

## Inspect and troubleshoot the result

The result page reports evaluated decisions, opened and closed orders, profit
factor, average/median/maximum realized-equity drawdown, initial and ending
capital, and configured additional trade costs. Some trade metrics are shown
as unavailable when no trades closed. Use the return link to reconfigure the
test.

- **No candles found:** check the selected pair and timeframe and compare the
  requested window with the stored dates. This walkthrough requires the Tinker
  insertion to have completed.
- **No trades:** expected for this flat smoke-test dataset. With imported data,
  inspect warm-up coverage, active hours/days, and whether the strategy had a
  crossing signal; a valid run can still have no trades.
- **Validation errors:** check the date format and ordering, positive capital,
  and that the selected timeframe and pair exist.
- **Server or database error:** inspect `storage/logs/laravel.log` and the
  server log shown in [Installation](installation.md). Check the local
  database connection before retrying.

## Use historical data for research

For a research backtest, create a market type, a market pair, and a timeframe
through the corresponding Workbench pages, then open `/workbench/data-import`.
The Alpha Vantage FX connector requires a provider credential for that request;
the credential is not stored by Nikaido. Its available history and request
limits are external prerequisites, so results depend on the supplied source.
Confirm that at least 200 warm-up candles precede the selected test range and
that the desired test range contains candles.

Alternatively, use the incoming-candle receiver on the Data Import page. Start
a session for one pair and timeframe, copy the bearer token while it is shown
(it is displayed only once), and POST OHLC data to
`/api/data-import/candles`. A request body can look like this:

```json
{
  "candles": [
    {
      "time": "2020-07-19 12:00:00",
      "open": 1.100000,
      "high": 1.101000,
      "low": 1.099000,
      "close": 1.100500
    }
  ]
}
```

Set the HTTP `Authorization` header to the `Bearer` scheme followed by the
one-time token. Each request accepts 1–1,000 candles; timestamps must use
`Y-m-d H:i:s`, and general OHLC values are required. Bid/ask OHLC is optional.
Repeated timestamps in later requests update the stored candle for that pair
and timeframe. Stop the receiver session when importing is complete so its
token can no longer be used. Do not paste credentials or bearer tokens into
source control or shared logs.

Before running a research test, filter the Market Prices page by the exact pair
and timeframe and review the date coverage. The included strategy has fixed
periods and does not expose a strategy selector or parameter form; those
settings are code-level. Execution costs and slippage are user-configured per
backtest, and no instrument-specific fees are inferred. See
[Configuration](configuration.md) for these assumptions and limitations.
