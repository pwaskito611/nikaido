# Testing architecture

Nikaido uses PHPUnit 11 through Laravel's test runner. The framework
configuration is in [`phpunit.xml`](../../phpunit.xml), dependencies and the
Composer test script are declared in [`composer.json`](../../composer.json),
and the application test base class is [`tests/TestCase.php`](../../tests/TestCase.php).

## Suites and organization

`phpunit.xml` defines two suites:

| Suite | Directory | Current contents |
|---|---|---|
| `Unit` | [`tests/Unit/`](../../tests/Unit/) | Focused behavior tests for Backtest configuration, domain/data types, execution helpers, repository-adjacent/domain operations, Strategy decision generation, and Workbench analysis or result-combination logic. |
| `Feature` | [`tests/Feature/`](../../tests/Feature/) | Laravel application tests for HTTP/Workbench behavior, ingestion, market-type backtest dispatch/results, repository CRUD/query behavior, migrations, and persisted model behavior. |

The names are the configured PHPUnit suite names; test file and namespace
names include some legacy groupings such as `Engine`, `Workbench`, and
`Strategy`. There is no separately configured `Integration` suite or
`tests/Integration/` directory in the current tree.

Tests in both suites can extend `Tests\TestCase`, which extends Laravel's
`Illuminate\Foundation\Testing\TestCase`. Unit tests for isolated logic may
instead extend `PHPUnit\Framework\TestCase`, as the partial-fill unit test
does. Feature tests use Laravel facilities such as HTTP request assertions,
`RefreshDatabase`, Eloquent models, database assertions, and queue fakes.

## What the suites exercise

- Strategy unit tests supply candle-shaped values and assert generated
  decisions and price fallbacks.
- Backtest unit tests exercise configuration/domain values, execution helpers,
  analysis, and portfolio ordering/allocation without requiring every case
  to go through an HTTP request.
- Feature tests exercise the boundaries between Laravel requests, validation,
  Actions, repositories, Eloquent persistence, queues, and result pages. For
  example, `WorkbenchFeatureTest` checks Workbench pages and chart selection;
  `MarketTypeBacktestTest` fakes queued jobs while checking run/pair records;
  and `DataImportFeatureTest` tests import-session and candle workflows.
- `EngineRepositoriesTest` directly exercises repository CRUD and candle
  retrieval. `DatabaseMigrationsTest` asserts schema and selected index
  expectations. These are in the `Feature` suite despite their names.

The suite layout shows which cases exist; it does not by itself establish
coverage completeness or a guarantee that every branch, route, or database
backend is tested.

## Test database and application configuration

`phpunit.xml` sets `APP_ENV=testing`, uses SQLite with `DB_DATABASE=:memory:`,
and sets cache and session stores to `array`. The default queue connection is
`sync` unless a test changes it. Tests using Laravel's `RefreshDatabase`
trait exercise migrations against the configured test database.

The default in-memory database setting is the PHPUnit configuration, not a
claim about a developer's local runtime connection. Tests or local overrides
can alter configuration: `MarketTypeBacktestTest`, for example, selects the
database queue and fakes the bus when verifying queued dispatch, then checks
that the sync queue is rejected.

## Running tests

From the repository root, the documented test command is:

```sh
php artisan test --compact
```

This runs Laravel's configured PHPUnit suites. The Composer `test` script
clears Laravel's cached configuration and then runs `php artisan test`; it
does not add a separate suite. To run one test file, pass its path:

```sh
php artisan test --compact tests/Unit/ExampleTest.php
```

The PHPUnit suite names are `Unit` and `Feature`; `phpunit.xml` also provides
the corresponding test directories. Use the project's normal
dependency-installation setup first so `vendor/bin` and PHPUnit are available.
