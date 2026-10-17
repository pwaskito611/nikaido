# Nikaido Documentation

These guides explain how to obtain, configure, and use a local Nikaido checkout.
Read them in order if you are new to the project.

## Architecture

These pages describe the current application structure and verified code
paths; they are intended for contributors, not as end-user feature procedures.

- [Architecture overview](architecture/overview.md) — major directories,
  application entry points, persistence relationships, and component boundaries.
- [Application flows](architecture/application-flow.md) — dashboard,
  market-data operations, backtest execution, and queued multi-pair flow.
- [Code organization](architecture/code-organization.md) — feature
  responsibilities and the roles of current application components.
- [Testing architecture](architecture/testing-architecture.md) — PHPUnit
  suites, test organization, configuration, and commands.

## Technical References

These pages provide implementation-oriented references for command and HTTP
surfaces, persisted schemas, and result structures.

- [Commands](reference/commands.md) — Nikaido-specific process commands and
  relevant Composer, Artisan, migration, queue, and test commands.
- [Routes and controllers](reference/routes-and-controllers.md) — registered
  route methods, names, middleware, request validation, and response behavior.
- [Data models](reference/data-models.md) — Eloquent models, migrated columns,
  indexes, relationships, casts, and deletion constraints.
- [Metrics and results](reference/metrics-and-results.md) — API, Workbench,
  and persisted result structures; formulas remain in the canonical
  [Performance metrics](features/performance-metrics.md) page.
- [PHP research ecosystem](reference/php-research-ecosystem.md) — optional
  libraries for quantitative research and choices for production PHP frameworks.

Installation and environment configuration are already covered by
[Installation](getting-started/installation.md) and
[Configuration](getting-started/configuration.md). The current strategy
convention is documented in [Strategies](user-guide/strategies.md); there is
no separate formal strategy interface or plugin contract.

## Concepts

These pages explain how to interpret Nikaido's simulation and its assumptions.
They complement the operating procedures in the User Guide and the detailed
implementation notes in Features.

- [Backtesting fundamentals](concepts/backtesting-fundamentals.md) — what
  the engine simulates, how to interpret historical results, and what would
  need extending for economic/fundamental research.
- [Order and execution semantics](concepts/order-and-execution-semantics.md) —
  order lifecycle, candle triggers, fills, and execution limitations.
- [Capital and risk](concepts/capital-and-risk.md) — sizing, realized
  accounting, and single/multi-pair capital constraints.
- [Robustness and stress testing](concepts/robustness-and-stress-testing.md) —
  the analyses Nikaido implements and what they cannot establish.

Exact metric formulas and edge cases are documented in
[Performance metrics](features/performance-metrics.md).

## Getting Started

1. [Installation](getting-started/installation.md) — install dependencies,
   prepare the local database, and start the application.
2. [Configuration](getting-started/configuration.md) — review environment
   settings, backtest parameters, and market-data requirements.
3. [First backtest](getting-started/first-backtest.md) — execute a deterministic
   synthetic smoke test and learn how to prepare historical data for research.
4. [Running tests](getting-started/running-tests.md) — run the Unit and Feature
   PHPUnit suites and diagnose common failures.

After installation, run pending database migrations from the project root with
`php artisan migrate`. Composer's create-project setup and `composer run setup`
run migrations automatically; use this command if they have not run yet or
after changing the configured database.

## User Guide

These guides explain how to use the implemented backtesting features after
setup. They describe the current application behavior; they do not imply
broker-grade execution or guarantee that historical results predict future
performance.

1. [Strategies](user-guide/strategies.md) — the current strategy contract,
   execution context, and how decisions become orders.
2. [Data management](user-guide/data-management.md) — add, import, inspect,
   edit, and delete stored market data.
3. [Market types and timeframes](user-guide/market-types-and-timeframes.md) —
   configure market groupings, pair dependencies, and timeframe labels.
4. [Market data](user-guide/market-data.md) — candle fields, timestamps,
   selection, and data-quality checks.
5. [Orders and positions](user-guide/orders-and-positions.md) — market and
   limit entries, fills, exits, and simulation assumptions.
6. [Capital and position sizing](user-guide/capital-and-position-sizing.md) —
   risk-based quantity, instrument units, realized capital, and shared capital.
7. [Fees and execution](user-guide/fees-and-execution.md) — costs, quotes,
   slippage, partial fills, and their limitations.
8. [Running backtests](user-guide/running-backtests.md) — repeatable single-pair
   and market-type workflows and result retrieval.
9. [Interpreting results](user-guide/interpreting-results.md) — metric
   definitions, edge cases, and robustness analysis.

## Features

These pages document the implementation semantics and constraints of individual
capabilities. They complement the operating procedures in the User Guide.

- [Backtest engine](features/backtest-engine.md) — chronological,
  event-driven candle simulation, input loading, execution order, capital
  changes, and failure behavior.
- [Data ingestion and validation](features/data-ingestion-and-validation.md) —
  Alpha Vantage example, provider replacement points, owned-data ingestion,
  push API behavior, validation, duplicate updates, and failure responses.
- [Market data management](features/market-data-management.md) — CRUD,
  persistence, relationships, uniqueness, and deletion behavior.
- [Market types and timeframes](features/market-types-and-timeframes.md) —
  identifiers, validation, dependencies, and backtest selection semantics.
- [Market support](features/market-support.md) — market records, pair units,
  candle fields, timeframe semantics, and unsupported mechanics.
- [Multi-pair backtesting](features/multi-pair-backtesting.md) — pair
  eligibility, independent runs, shared/separate capital, and event ordering.
- [Order types](features/order-types.md) — market and limit entries, stop/target
  exits, close instructions, and their state behavior.
- [Limit orders](features/limit-orders.md) — trigger prices, fill prices,
  partial fills, cancellation, gaps, and OHLC assumptions.
- [Shared capital](features/shared-capital.md) — synchronized risk allocation,
  reservation, capital accounting, and allocation constraints.
- [Transaction costs](features/transaction-costs.md) — quote selection,
  slippage, fee formulas, holding costs, and rounding limits.
- [Performance metrics](features/performance-metrics.md) — implemented
  performance measures, definitions, and empty-data behavior.
- [Stress testing](features/stress-testing.md) — the historical-excursion
  price stress scenario and its exact scope.
- [Robustness analysis](features/robustness-analysis.md) — stress analysis,
  chronological clusters, Monte Carlo, and what those analyses do not prove.
- [Monte Carlo](features/monte-carlo.md) — trade-sequence shuffling and
  bootstrap simulation.
- [Concurrency](features/concurrency.md) — queued pair jobs, retries,
  persistence, aggregation, and limits on parallel execution.

## Project References

- [Project overview](PROJECT.md) describes Nikaido's purpose and design
  principles.
- [Coding standards](coding-standar.md) documents conventions used in the
  codebase.
- [Getting Started](getting-started/) covers installation, initial
  configuration, a first smoke test, and running tests.
