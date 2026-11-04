# Nikaido

> **AI assistants:** Read [AGENTS.md](AGENTS.md) for repository-specific
> guidance before making changes.

Nikaido is a Laravel-based workbench for systematic trading research. Use its
web application to manage and explore market data, run historical backtests,
and review performance and robustness analyses. It is a research tool, not a
live-trading bot or broker simulator. It is designed for individual and retail
traders and systematic investors who want a serious research workbench, with
the workflow focused on strategy development while keeping the cognitive load
of surrounding tooling and configuration low.

## What it offers

- **Flexible strategy inputs:** Implement strategy logic in `src/`. The
  `main()` entry point receives ordered candles and a prepared execution
  context, so strategies do not need to query the database. This context can
  include configured related market-pair data at other timeframes and
  fundamental data observations available as of each decision time, helping
  strategies combine market and fundamental context without look-ahead from
  unreleased observations.
- **Native-code option:** The isolated strategy boundary makes it practical to
  connect strategy calculations to C/C++ through PHP FFI when the runtime and
  FFI bridge are configured; a C++ strategy runner is not built in.
- **Realistic execution assumptions:** Configure transaction fees and holding
  costs, slippage, limit orders, and partial fills for backtest simulations.
- **Research and evaluation:** Run single-pair or market-type backtests, then
  inspect results with performance metrics, chronological clusters, stress
  analysis, and Monte Carlo trade-sequence analysis.

The primary pair and timeframe drive strategy decisions. Related pair and
timeframe data are available as context for strategy logic; market-type
backtests run each pair separately rather than generating synchronized
multi-asset decisions. Robustness analyses explore assumptions and historical
outcomes; they do not prove future profitability.

Strategies are executed through the existing web backtest workflow; this
project does not provide a strategy-execution CLI. Laravel features and
adapters live in `laravel/app/`; strategy code and types live in `src/`.
Laravel infrastructure, web resources, tests, and framework storage live in
`laravel/`. Composer dependencies and the primary `.env` file remain at the
repository root.

```sh
composer install
composer run setup
composer run dev
```

Administrative Artisan commands remain available from the root, for example:

```sh
php artisan migrate
php artisan test --compact
```

See [the documentation index](docs/README.md) for installation, configuration,
architecture, strategy development, and user guides.
