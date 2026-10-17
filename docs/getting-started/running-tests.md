# Running Tests

Nikaido uses PHPUnit through Laravel's Artisan test command. The development
dependency constraint is PHPUnit `^11.5.50`; `composer.lock` currently resolves
it to PHPUnit `11.5.57`.

## Test layout and environment

- `tests/Unit/` covers domain calculations, configuration objects, strategy
  behavior, execution helpers, and analysis logic.
- `tests/Feature/` covers Laravel-facing behavior such as data import,
  migrations, repositories, Workbench requests, and backtest flows.
- `tests/TestCase.php` provides the Laravel test application base class.

`phpunit.xml` configures the test environment to use SQLite `:memory:`,
`APP_ENV=testing`, array-backed cache and sessions, and a synchronous queue.
Feature tests therefore do not need the local development database, a running
MySQL service, the Nikaido queue worker, or external provider credentials.
Database tests create their own test state; do not migrate or seed the
development database just to run PHPUnit.

## Run the complete suite

From the project root:

```sh
php artisan test --compact
```

The command runs both configured suites. A successful exit status and a
passing PHPUnit summary indicate that the executed tests passed; a partial run
or a filtered test is not evidence that the full suite passed.

The Composer script is also available:

```sh
composer test
```

It clears Laravel's configuration cache and then runs `php artisan test`.

## Run focused tests

Run either suite by name:

```sh
php artisan test --compact --testsuite=Unit
php artisan test --compact --testsuite=Feature
```

Pass a test file to limit the run, or use PHPUnit's test-name filter:

```sh
php artisan test --compact tests/Unit/Engine/Domain/SpecificPairBacktestDomainTest.php
php artisan test --compact --filter=SpecificPairBacktest
```

Useful focused tests include:

- `tests/Unit/Engine/Domain/SpecificPairBacktestDomainTest.php` for simulation
  and order behavior.
- `tests/Unit/Strategy/Actions/GenerateMovingAverageCrossDecisionsActionTest.php`
  for the included strategy.
- `tests/Unit/Workbench/Domain/MarketTypeBacktests/PortfolioCapitalSynchronizationDomainTest.php`
  for chronological shared-capital allocation.
- `tests/Feature/RunSpecificPairBacktestActionFeatureTest.php` for the specific
  pair workflow.
- `tests/Feature/DataImportFeatureTest.php` for incoming and external data
  import behavior.

The paths above are examples from the current test tree; use a focused test
when changing one behavior, then run the full suite for changes spanning
features or shared execution paths.

## Interpret failures

- **Missing test dependencies:** run `composer install` and confirm the PHP
  executable used by Composer and Artisan satisfies Composer's PHP `^8.2`
  requirement (PHP 8.2 through 8.x). Use
  `composer check-platform-reqs` to identify missing PHP extensions.
- **SQLite driver errors:** install/enable `pdo_sqlite` for the CLI PHP version.
  The test suite uses in-memory SQLite even if `.env` selects MySQL.
- **Test tries to connect to MySQL or Redis:** check that the project-root
  `phpunit.xml` is being loaded and that the command is run from the repository
  root. Clear a stale Laravel configuration cache with `php artisan
  config:clear`, then rerun the tests.
- **External API or queue is unavailable:** feature tests should use their
  test configuration and do not require provider credentials or a running
  queue worker. Check the failure trace for an accidentally invoked live
  integration before changing local service settings.
- **Migration or database-state failure:** inspect the failing test and its
  database-refresh setup. PHPUnit's `:memory:` database is separate for a test
  run; avoid “fixing” this by resetting the local development database.
- **Assertion failure:** use the first failing assertion and its test fixture
  as the diagnostic. Do not treat a passing subset as a passing full suite.

Tests validate implementation behavior against their fixtures; they do not
validate the quality or completeness of a user's external historical data, and
they do not establish future trading performance.
