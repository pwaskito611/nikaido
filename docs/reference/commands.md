# Commands

This page lists the Nikaido-specific commands and the Laravel/Composer
operations used to set up, test, migrate, and operate this application. It is
based on the root `composer.json`, the registered Artisan commands, and the
command implementations.

For PHP, frontend, database-driver, and environment setup requirements, see
[Installation](../getting-started/installation.md) and
[Configuration](../getting-started/configuration.md).

## Nikaido-specific Artisan commands

| Command | Arguments and options | Effect |
|---|---|---|
| `php artisan nikaido:start` | `--host=127.0.0.1`, `--port=8000` | Starts Laravel's development server and a `queue:work` worker as managed child processes. |
| `php artisan nikaido:stop` | None | Stops the managed worker and server, if running. |

`--host` must not be empty. `--port` must parse as an integer from 1 through
65535. The host is passed to Laravel's development server without further
address validation.

The start and stop commands require Linux, `/proc`, the PHP POSIX extension,
and `nohup`. The manager records PIDs and writes logs to
`storage/logs/nikaido-server.log` and `storage/logs/nikaido-worker.log`.
Startup errors are reported by the command; if starting either process fails,
the manager attempts to stop any process it started during that invocation.
Stopping sends `SIGTERM` first and can escalate to `SIGKILL` if necessary.
These are development process helpers, not a production process supervisor.

The worker started by `nikaido:start` runs `queue:work --tries=1 --timeout=0`.
Market-type backtests require a queue connection other than `sync`; see
[Concurrency](../features/concurrency.md).

## Setup and development

| Command | Definition and effect |
|---|---|
| `composer run setup` | Composer script: installs PHP dependencies, creates `.env` from `.env.example` if absent, generates an application key, runs `php artisan migrate --force`, installs npm dependencies, and builds frontend assets. On a fresh checkout using SQLite, create `database/database.sqlite` first; the script does not create the file. |
| `composer run dev` | Runs `php artisan serve`, `php artisan queue:listen --tries=1 --timeout=0`, `php artisan pail --timeout=0`, and `npm run dev` through `npx concurrently`. Requires frontend dependencies. |
| `php artisan key:generate` | Generates the Laravel application key in the environment file. The Composer setup script invokes it. |
| `php artisan nikaido:start --host=127.0.0.1 --port=8080` | Example of overriding the managed server's default host and port. |

The two development entry points use different queue commands: `composer run
dev` uses `queue:listen`, while `nikaido:start` uses `queue:work`. Both start a
worker. Neither custom Nikaido command imports data or runs a backtest; those
operations are exposed through the Workbench and the HTTP routes described in
[Routes and controllers](routes-and-controllers.md).

## Database and queue operations

These are Laravel Artisan commands, not Nikaido-specific command definitions:

| Command | Use |
|---|---|
| `php artisan migrate` | Apply pending migrations to the configured database. |
| `php artisan migrate:status` | Show which migrations have run. |
| `php artisan migrate:rollback` | Roll back the last migration batch. This changes the schema and may remove data; inspect the migrations and database before using it. |
| `php artisan queue:work` | Process queued jobs. Use the configured queue connection and keep a worker running for market-type backtests. |
| `php artisan queue:failed` | List failed queued jobs. |
| `php artisan queue:retry <id>` | Retry a failed job by its identifier. |

The queue tables are created by the repository's Laravel queue migration.
Changing the queue connection or running a worker is an operational choice;
the application does not provide a custom queue-management command.

## Tests and configuration cache

The configured test suites are PHPUnit's `Unit` and `Feature` suites:

```sh
php artisan test --compact
php artisan test --compact --testsuite=Unit
php artisan test --compact --testsuite=Feature
composer test
```

`composer test` clears Laravel's configuration cache and runs
`php artisan test`. The test database and environment overrides are defined in
[`phpunit.xml`](../../phpunit.xml); see
[Testing architecture](../architecture/testing-architecture.md) for their
scope and limitations.

`php artisan config:clear` removes a cached Laravel configuration file. Use it
after changing `.env` when configuration caching is in effect; ordinary local
development does not require `config:cache`.

## Other Artisan commands

The application also exposes Laravel's standard Artisan command set, including
`route:list`, `db:table`, `make:*`, and `queue:*`. They are provided by Laravel
or installed packages, not custom Nikaido command implementations. To inspect
the exact commands available in the current environment, run:

```sh
php artisan list
```
