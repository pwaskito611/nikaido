# Configuration

Nikaido uses Laravel configuration files as the boundary between environment
values and application services. Local values belong in the untracked `.env`
file. Configuration files read those values, and application code should
consume Laravel configuration rather than call `env()` directly. The
`.env.example` file documents the supplied local defaults.

## Local environment variables

The following values affect the normal local setup. Values shown as defaults
come from `.env.example`; leave optional values unchanged unless you use the
corresponding integration.

Except for `APP_KEY` and settings that must match a database you explicitly
select, the values below are optional when their defaults fit your local
environment. Unset values fall back to the listed defaults or Laravel's
configured fallback. Use the shown URL, boolean, integer, path, and credential
formats; a misspelled driver, malformed URL, or invalid port will fail when the
corresponding service is used.

| Variable | Default / format | Purpose and guidance |
|---|---|---|
| `APP_NAME` | `Nikaido` | Application label; also contributes to cache/session prefixes. |
| `APP_ENV` | `local` | Environment name. Keep `local` for development. |
| `APP_KEY` | Empty in template; generated key | Required for Laravel encryption and signed data. Run `php artisan key:generate`; never publish the key. |
| `APP_DEBUG` | `true` | Enables detailed local error pages. Do not enable debug output on a public or production deployment. |
| `APP_URL` | `http://localhost` | Base URL used by generated links and assets. Change it only when your local host/port differs. |
| `APP_LOCALE` | `en` | Application locale. |
| `APP_FALLBACK_LOCALE` | `en` | Fallback locale for translations. |
| `APP_FAKER_LOCALE` | `en_US` | Locale used by Faker in development/test data generation. |
| `APP_MAINTENANCE_DRIVER` | `file` | Laravel maintenance-mode storage. The default needs no database table. |
| `BCRYPT_ROUNDS` | `12` | Laravel password-hashing cost setting. It does not affect backtest parameters. |
| `DB_CONNECTION` | `sqlite` | Default database driver. |
| `DB_DATABASE` | `database/database.sqlite` | SQLite file path relative to the project root. Create the file on a fresh clone before running the scripted setup. |
| `DB_HOST` | Commented in template; `127.0.0.1` when using MySQL | Database server hostname. Only relevant for a server database. |
| `DB_PORT` | Commented in template; `3306` for MySQL | Database server port. |
| `DB_USERNAME` | Commented in template; `root` is Laravel's configured fallback | Database account name. Prefer a local account with only the required privileges. |
| `DB_PASSWORD` | Commented in template; empty fallback | Database password. Keep it local and do not commit it. |
| `SESSION_DRIVER` | `database` | Session storage. The default requires migrations to have completed. |
| `SESSION_LIFETIME` | `120` minutes | Session idle lifetime. |
| `SESSION_ENCRYPT` | `false` | Controls session encryption. |
| `SESSION_PATH` | `/` | Cookie path. |
| `SESSION_DOMAIN` | `null` | Cookie domain; leave unset for local development. |
| `QUEUE_CONNECTION` | `database` | Queued jobs use the database. `nikaido:start` starts the worker. |
| `CACHE_STORE` | `database` | Cache uses the database, so migrations and a working DB connection are required. |
| `CACHE_PREFIX` | Derived from the app name | Optional cache key prefix for shared cache stores. |
| `LOG_CHANNEL` | `stack` | Selects the configured Laravel log channel. |
| `LOG_STACK` | `single` | Channel used by the default stack. |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Optional deprecation log channel. |
| `LOG_LEVEL` | `debug` | Local log verbosity. |
| `BROADCAST_CONNECTION` | `log` | Local broadcast driver; no external broadcaster is required. |
| `FILESYSTEM_DISK` | `local` | Default filesystem disk. |
| `MAIL_MAILER` | `log` | Writes mail output to logs; no mail server is required. |
| `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT` | `null`, `127.0.0.1`, `2525` | Only configure these when selecting a network mailer. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | `null` | Optional mail-server credentials. Keep secrets out of source control. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `hello@example.com`, app name | Sender values used if mail is configured. |
| `VITE_APP_NAME` | Derived from `APP_NAME` | Frontend build value; it is not backtest configuration. |

The template also exposes optional Memcached, Redis, AWS, and other Laravel
integration variables (`MEMCACHED_HOST`, `REDIS_CLIENT`, `REDIS_HOST`,
`REDIS_PASSWORD`, `REDIS_PORT`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
`AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `AWS_USE_PATH_STYLE_ENDPOINT`). They
are not required by the default local workflow. Configure them only if you
deliberately select a matching cache, queue, filesystem, or service driver.
Treat all credentials as secrets.

For less common server-database setups, Laravel's database configuration also
accepts `DB_URL` or driver-specific values such as `DB_SOCKET`, `DB_CHARSET`,
and `DB_COLLATION`; these are not needed for the default SQLite setup and are
not populated in `.env.example`.

`STRATEGY_SERVICE_URL` is present in `.env.example`, but a search of the current
application code found no runtime reference to it. It does not select or
configure the included strategy. The strategy credential for Alpha Vantage is
entered for an individual import request in the Data Import page and is not
saved by the application.

## Database setup and verification

The default local configuration is:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

If using MySQL, set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`; create the database and ensure
the PHP MySQL PDO driver is available before running migrations. A refused
connection indicates the configured server/port is unreachable, not that the
market data is missing.

After changing `.env`, verify the selected connection and migration state with:

```sh
php artisan migrate:status
```

This contacts the configured database and reports migration state. If Laravel
configuration was intentionally cached earlier, clear that local cache after
editing `.env`:

```sh
php artisan config:clear
```

Do not run `config:cache` during routine local development; it can make later
`.env` edits appear to have no effect. The setup procedure and complete local
database prerequisites are covered in [Installation](installation.md).

## Backtest configuration

Backtest settings are submitted per run through
`/workbench/tests/create`; they are not global `.env` settings.

- **Test scope:** one market pair, or all eligible pairs in a market type.
  Market-type runs may use shared or separate starting capital; both modes
  queue work, so keep the queue worker running. Shared mode additionally
  synchronizes risk allocation across pairs.
- **Timeframe and window:** choose a configured timeframe and a date/time range
  covered by that pair's historical candles. The application uses its local
  time for the window. You can run all day, set active hours, and exclude ISO
  weekdays (Monday `1` through Sunday `7`).
- **Starting capital and quantity:** initial capital must be positive and have
  no more than two decimal places. For one pair it is expressed in that pair's
  quote currency. The pair's quantity unit (`units`, `contract`, or `lot`) and
  contract size inform quantity sizing.
- **Strategy:** the Workbench currently uses the included moving-average
  crossover strategy; it does not offer a strategy selector or strategy
  parameter form. The example uses 20- and 50-period EMAs with a 200-period
  trend average, requests risk of 1% of current capital per entry, and targets
  twice the entry-to-stop distance. A strategy decision supplies the requested
  quantity, stop, and target.
- **Execution assumptions:** fixed slippage and synthetic partial fills are
  optional and default off. Partial-fill ratio must be greater than zero and
  at most one; the unfilled remainder can be kept or cancelled. This is not a
  liquidity model and does not use candle volume or an order book.
- **Additional costs:** commission, exchange, clearing, regulatory, and
  transaction-tax charges can be configured per order, as a percentage of
  transaction value, or per quantity. Borrow and financing costs use a daily
  notional percentage. No broker, tax, or market schedule is inferred;
  disabled or unconfigured costs are omitted.

The current single-pair result is returned to the Workbench session. A
market-type run is queued and stored for later review. Stress and Monte Carlo
analysis are optional result-page analyses, not global runtime settings.

## Market-data prerequisites

The backtest consumes historical records for a specific pair and timeframe,
ordered by `price_date` and then record ID. A record has a pair, timeframe,
timestamp (`Y-m-d H:i:s`), and general OHLC prices; bid/ask OHLC fields are
optional. The Workbench validates positive prices with up to six decimal
places, and checks that the timestamp is unique for the selected pair and
timeframe. The incoming-data API additionally checks candle high/low
consistency.

Data can be created in the Market Prices Workbench, imported through the
Alpha Vantage FX connector, or pushed to a short-lived bearer-token API
session. Alpha Vantage's connector supports daily, weekly, and monthly data,
minute intervals of 1, 5, 15, 30, or 60 minutes, and whole-hour intervals
that divide 24. Provider coverage and limits apply. Provider-imported candles
contain generic OHLC; they do not include synthesized bid/ask data.

The strategy needs 200 candles before it can evaluate its trend filter. The
repository includes up to 200 earlier candles as warm-up data before the
selected start date. If the stored history is shorter, or there is no valid
crossing signal, a valid run can produce no orders. Filter the Market Prices
page by pair and timeframe and compare the stored timestamps with the intended
window before submitting a run.

## Configuration troubleshooting

- **A value seems unchanged:** confirm the correct `.env` is being used, then
  run `php artisan config:clear` if a configuration cache exists.
- **Connection refused:** verify the selected database driver and that the
  configured database service is running; use SQLite if no server is intended.
- **No market data:** verify the pair, timeframe, and date range together.
  Candles for another timeframe or a timestamp outside the selected range do
  not count.
- **The run completes without trades:** confirm there is enough warm-up
  history, that the selected active schedule includes candles, and that the
  strategy actually produced a signal. A no-trade result is not evidence of a
  failed database configuration.
- **Unexpected costs or quantities:** review pair quantity unit and contract
  size, the chosen capital unit, enabled cost components, and execution
  assumptions. Rates are user supplied; the application does not infer a
  broker's rules.

For a complete walkthrough, see [First backtest](first-backtest.md).
