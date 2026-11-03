# Nikaido

Nikaido is a Laravel-based workbench for exploring market data, running
strategy-driven backtests through the UI, and evaluating their results.
Strategies are selected and executed through the existing web workflow; this
project does not provide a strategy-execution CLI.

Laravel features and their adapters live in `laravel/app/`. The strategy
implementation and strategy types live in `src/`, with `src/main.php` called
through Laravel's Strategy adapter during UI-triggered backtests. Laravel
infrastructure, web resources, tests, and framework storage live in
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
