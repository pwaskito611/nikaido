# Installation

This guide prepares a local Nikaido development environment. The commands below
assume Linux, the platform required by Nikaido's bundled `nikaido:start` and
`nikaido:stop` process commands.

## Prerequisites

- PHP 8.2 through 8.x (the Composer requirement is `^8.2`) and Composer.
- PHP PDO with the SQLite PDO driver (`pdo_sqlite`) for the default local
  database. The Composer package requirements also need the usual Laravel PHP
  extensions, including OpenSSL, Fileinfo, tokenizer, XML/DOM, and JSON.
- Node.js 20 or later and npm to install and build the Vite frontend assets;
  locked frontend dependencies include packages requiring Node 20+.
- Linux and the PHP POSIX extension for `nikaido:start` and `nikaido:stop`.
  These commands also use `/proc` to track their child processes.
- No separate database server, queue broker, Redis service, or mail service is
  required for the default local setup. SQLite, database-backed cache/session/
  queue, and log mail are configured in `.env.example`.

The project requires PHP `^8.2`, Laravel `^12.0`, and PHPUnit `^11.5.50` for
development. Here, `^8.2` means PHP 8.2 or newer within the 8.x major version;
it does not express support for PHP 9. The exact resolved framework and
test-runner versions are recorded in `composer.lock`.

## Create a project

```sh
composer create-project pandu/nikaido nikaido
cd nikaido
```

Composer creates the `nikaido` directory and installs the project. You should
now be in the directory containing `artisan`, `composer.json`, and
`package.json`. For details about settings, continue to
[Configuration](configuration.md).

## Build the frontend

Install the Node dependencies and build the Vite assets:

```sh
npm install
npm run build
```

The Composer create-project lifecycle initializes `.env`, generates the
application key, creates the default SQLite database file, and runs migrations.
If you select another database, configure its connection in `.env` and run
`php artisan migrate` after project creation. This setup is intended for local
development, not production.

For a project that was cloned from the repository rather than created with
Composer, run `composer run setup` from the project root; create
`database/database.sqlite` first when using the default SQLite configuration.

## Database preparation

SQLite is the default and the database file is `database/database.sqlite`.
Migrations create the application's tables, including those used by the
database-backed cache, sessions, and queue. There is no market-data seeder:
after setup, the database has no market types, pairs, timeframes, or historical
prices. Add or import those before attempting a backtest; see
[First backtest](first-backtest.md).

If you select MySQL or another connection configured by Laravel, create the
database first, install the matching PHP PDO driver, and set the connection
variables in `.env` before migrating. Nikaido's default setup and test
configuration use SQLite; other database engines are not exercised by this
guide.

## Start the application

From the project root on Linux:

```sh
php artisan nikaido:start
```

The command starts the web server at `http://127.0.0.1:8000` and a queue worker.
It reports both process IDs and writes logs to
`storage/logs/nikaido-server.log` and `storage/logs/nikaido-worker.log`. The
queue worker is needed for queued market-type backtests. The command accepts
`--host` and `--port`, for example:

```sh
php artisan nikaido:start --host=127.0.0.1 --port=8080
```

Open `http://127.0.0.1:8000/up` to check the Laravel health route, then visit the
Workbench. Stop both managed processes with:

```sh
php artisan nikaido:stop
```

## Installation checklist

- `composer create-project pandu/nikaido` completes without PHP platform or
  migration errors.
- `npm run build` produces the Vite assets without a missing-manifest error.
- `php artisan nikaido:start` reports both server and worker as running.
- `/up` responds and the Workbench loads in the browser.
- The Workbench is expected to have no market data on a clean database.

## Troubleshooting

| Symptom | Diagnose | Corrective action |
|---|---|---|
| Composer reports an unsupported PHP version or missing extension | Run `php -v` and `composer check-platform-reqs` using the same PHP binary that runs Artisan. | Install a PHP version satisfying `^8.2` and the missing extension for that CLI PHP installation, then rerun `composer install`. For SQLite, check that `pdo_sqlite` is enabled. |
| Migration reports “unable to open database file” | Check that `.env` selects `DB_CONNECTION=sqlite`, that `DB_DATABASE` points to the expected path, and that the file's parent directory is writable. | From the project root, run `touch database/database.sqlite`, then run `php artisan migrate`. This creates an empty file if needed; it does not reset an existing database. |
| MySQL reports “Connection refused” | Check `DB_CONNECTION`, `DB_HOST`, and `DB_PORT` in `.env`; verify that the MySQL service is running and listening at that host and port. | Start the intended database service or restore the default SQLite settings. A connection-refused error occurs before database authentication; changing the password alone will not fix an unavailable server. |
| “Access denied” or “Unknown database” | The server is reachable, but the configured account lacks access or the database has not been created. | Create the database and grant the configured local account access, then verify `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. |
| Migration fails after changing database drivers | Confirm the selected driver is installed and the database exists. Use `php artisan migrate:status` to check what has already run. | Correct the driver/connection configuration and rerun `php artisan migrate`. Avoid `migrate:fresh` unless you intentionally want to drop all tables and data. |
| `nikaido:start` says Linux/POSIX is required | The bundled start/stop commands use Linux `/proc` and PHP `posix_kill`. | Run on Linux with the POSIX extension enabled. Do not assume the custom process-management command works on Windows or macOS. |
| Start command cannot create PID or log files | Inspect permissions on `storage/` and `bootstrap/cache/` as the current user. | Grant the development user write access to the specific Laravel runtime directories. Avoid broad permission changes over the repository. |
| Vite reports a missing manifest or frontend assets are absent | Check whether `npm run build` completed and whether `public/build/manifest.json` exists. | Run `npm install` and `npm run build` from the project root; read the first Vite error for Node/npm incompatibilities. |
| Setup stops during Composer or npm installation | Check network access, the reported package name, and the runtime version in use. | Correct the reported platform/network issue and rerun the failed install command. Do not delete lock files to work around dependency errors. |
