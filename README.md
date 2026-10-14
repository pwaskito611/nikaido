<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Application structure

Application code is grouped under two top-level parents:

- `app/Features/` contains user-facing capabilities. `Engine`, `Workbench`, and
  `DataImport` own their use cases and the Laravel adapters they need, such as
  controllers, requests, jobs, and routes.
- `app/Platform/` contains shared or technical capabilities. `Strategy` provides
  strategy logic used by Engine, `Console` contains Artisan commands, and
  `Infrastructure` contains framework wiring and external-system adapters.

Feature modules may depend on Platform capabilities; Platform code should not
depend on a specific Feature. A module only needs the layers it actually uses.
For example, Strategy does not need controllers or routes.

## Monte Carlo simulation

The Workbench results page can run trade-sequence Monte Carlo simulations from the
closed trades already stored in the backtest result. It does not rerun the strategy
or modify the source order history. The input P/L is the backtest's `profit_loss`;
this repository does not currently calculate separate commissions or fees.

Choose `shuffle` to sample every historical trade exactly once in a randomized
order, or `bootstrap` to sample with replacement. Bootstrap may use a configured
number of trades per simulation and treats trades as independent observations; it
does not preserve temporal or market-regime dependencies. Shuffle changes order
only and does not change the historical P/L distribution.

Simulation configuration defaults to 1,000 simulations, all historical trades per
simulation, shuffle, the original backtest initial capital, fixed monetary P/L,
and a 20% drawdown threshold. The optional seed can be set from 0 to 2,147,483,647;
without a seed, a fresh random seed is generated. A configured trade count other
than the historical count is accepted only for bootstrap. Requests are limited to
10,000,000 simulated trade steps.

Equity can use fixed monetary P/L or compounded return. The compounded return for
each historical trade is derived from its stored `profit_loss / capital_at_open`;
it is rejected when the denominator is missing or invalid. Equity reaching zero
or below is floored at zero and the simulation stops processing remaining trades.
Fixed monetary P/L preserves each nominal trade P/L; compounded-return simulations
preserve each trade's derived return instead.
Maximum drawdown is measured from the running equity peak and reported both as a
percentage of peak equity and as a monetary amount. Drawdown probability uses a
configurable percentage threshold. Percentiles use linear interpolation between
the adjacent sorted simulation outcomes.

The report includes final-equity and maximum-drawdown distributions, percentiles,
loss and drawdown probabilities. A 95th-percentile drawdown is a simulation
distribution statistic, not a guaranteed loss bound. Neither method models future
changes in spread, slippage, liquidity, volatility, or market regime, guarantees
future performance, or removes overfitting risk.

## Parallel market-type backtests

The Workbench supports both the original synchronous specific-pair test and a
parallel market-type test. The latter selects pairs with at least one candle in
the requested timeframe and date range, stores a parent run plus one status/result
row per pair, then dispatches one queue job per eligible pair. Each job invokes
the same `RunConfiguredPairBacktestAction` used by the specific-pair workflow. Empty
eligible sets complete immediately. Pair failures are recorded independently and
do not stop other queued jobs; the run becomes `completed_with_failures` after
all pair records reach a terminal state.

Backtest execution, parallel-run coordination, queue jobs, result persistence,
Fluctuation Stress Test, and Monte Carlo calculations live in the Engine module.
Workbench remains the HTTP/form and presentation layer and delegates calculations
to Engine actions.

Each entry strategy response supplies a positive quantity along with the signal
and SL/TP levels. The moving-average example sizes positions to risk 1% of the
current realized capital, updated after each candle as exits are filled. Strategies
receive a mutable execution context containing that capital and the selected pair's
quantity unit and multiplier. Each market pair defines how strategy quantity is
interpreted: `units` (including shares or quantity) pass through directly;
`contract` retains the number of contracts and uses the configured contract point
value to scale P/L and stop risk; `lot` converts lots to underlying units using the
configured units per lot. Realized P/L and stop risk use actual filled quantities.
Futures point value is assumed to be denominated in the pair's quote currency; the
engine does not convert settlement currencies or model margin. The pair editor
defaults new pairs to `units`; pairs created before the unit setting was introduced
are treated as lots to preserve their existing multiplier.

For local development on Linux, start the Laravel development server and one
queue worker together:

```sh
php artisan nikaido:start
```

The server listens on `127.0.0.1:8000` by default. Override its address with
`--host` and `--port`, for example `php artisan nikaido:start --port=8080`.
Stop both managed processes with:

```sh
php artisan nikaido:stop
```

The commands track their own process IDs under
`storage/framework/nikaido-processes` and write output to
`storage/logs/nikaido-server.log` and `storage/logs/nikaido-worker.log`.
They require Linux and PHP's POSIX extension. The worker consumes the
application's configured default queue connection.

The Market Exploration page displays historical OHLC candles for a selected pair,
timeframe, and inclusive date range. Its naked candlestick chart has no indicators
and supports horizontal scrolling and drag navigation.

Workbench date, date-time, and time fields use Flatpickr with Indonesian display
formatting. Submitted values retain the existing `Y-m-d`, `Y-m-dTH:i`, and `H:i`
formats expected by the application.

Parallel runs require an asynchronous Laravel queue connection; the `sync`
driver is rejected so the request never executes all pairs inline. Database is
the default connection. Run workers against the configured queue, for example:

```sh
php artisan queue:work database --queue=default --tries=3 --timeout=60
```

To increase parallelism, run multiple worker processes or configure the process
count in the deployment's worker supervisor. Each process can claim a different
pair job. The job uses three attempts, short backoff intervals, a 60-second
timeout, and per-pair overlap protection. The queue connection's `retry_after`
must remain greater than the worker timeout; adjust it if the job timeout is
changed. Run progress and each saved pair result are available at the run page
returned immediately after submission.

Completed market-type runs provide pair detail pages and an overall result page.
The overall page combines candle decisions and trades from successful pairs in
time order and supports the same cluster, fluctuation stress, and Monte Carlo
analysis as a specific-pair result. Runs offer two capital modes. Shared portfolio
capital starts from one configured balance and synchronizes candidate trades
chronologically after pair workers complete. Exits release reserved stop-loss risk
and realize P/L before entries at the same time; simultaneous entries that exceed
available risk capital have their quantities reduced proportionally. Strategy
quantities otherwise remain unchanged. Separate capital per pair keeps each pair's
backtest independent and reports aggregate capital as the sum across successful
pairs, including average initial and ending capital per pair. Both modes combine
P/L as reported and do not perform FX conversion, so results must use a common
account unit. Runs with failures include only successful pair results in the
overall analysis.

## Data import

Workbench Data Import provides an on-demand external-provider pull and an API
receiver that is off unless an incoming session is active. The selected pair and
timeframe are passed to the configured provider connector along with a credential
entered for that request; the credential is not saved. The connector is selected
by the `ExternalMarketDataProvider` binding in `AppServiceProvider`, so a project
can replace it with an adapter for another provider. Provider-specific symbol
mapping, authentication, response parsing, available history, and timeframe
support belong inside that adapter. The included example adapter returns general
OHLC only, so bid/ask columns remain null instead of being synthesized. The
backtest falls back to general OHLC when bid/ask is missing, including using
general open for entry; this does not model a spread.

Starting an incoming session scopes it to one pair and timeframe and generates a
random bearer token. Only the token's SHA-256 hash is stored, and the raw token is
shown once. Stop the session when the sender is done. Send a JSON POST to
`/api/data-import/candles` with the token in the Authorization header and a
`candles` list of `time`, `open`, `high`, `low`, and `close`. Timestamps use
`Y-m-d H:i:s`; each request accepts up to 1,000 candles, and repeated timestamps
update the existing pair/timeframe candle. Bid/ask fields remain null when not
provided; send optional `bid_open/high/low/close` and `ask_open/high/low/close`
fields on each candle when quote-specific values are available.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
