# Nikaido — AI Assistant Guide

Use this guide as an initial orientation. For coding tasks, go directly to the owning module and its relevant tests. Do not scan the entire repository or reread all documentation unnecessarily.

## Project Facts

- Nikaido is a PHP 8.2 and Laravel 12 trading backtesting application. Tests use PHPUnit.
- Feature code lives in `app/Feature/`, under the `App\Feature\` namespace. Autoloading is defined in `composer.json`.
- `app/Platform/` contains cross-feature and technical capabilities, including Strategy, Console, and Infrastructure.
- Modules: `Dashboard`, `MarketTypes`, `MarketPairs`, `Timeframes`, `MarketPrice`, `DataImport`, `MarketExploration`, and `Backtest`.
- The actual directory uses `app/Feature/` (capital `F`). Some older documentation may refer to `app/feature/` or the former `Engine`/`Workbench` structure. Follow the current source code, Composer autoload configuration, and this guide when determining the current structure.

## Module Map

| Module | Primary area |
|---|---|
| `MarketTypes` | `/workbench/market-types` |
| `MarketPairs` | `/workbench/market-pairs` |
| `Timeframes` | `/workbench/timeframes` |
| `MarketPrice` | `/workbench/market-prices` |
| `DataImport` | `/workbench/data-import` |
| `MarketExploration` | `/workbench/market-exploration` |
| `Backtest` | `/workbench/tests/...` and API `/api/engine/specific-pair-test` |
| `Dashboard` | Application dashboard |

Each web module's routes are defined in `app/Feature/<Module>/routes.php`. API routes are defined in the `api.php` file of the module that owns them. Web and API route registration is handled through `routes/web.php` and `bootstrap/app.php`.

## Architectural Boundaries

- Controllers handle HTTP requests and invoke Actions belonging to their own module. **Never invoke an Action from another feature directly from a Controller.**
- Actions orchestrate operations. When cross-feature integration is necessary, coordinate it through the Action that owns the use case rather than through a Controller.
- Repositories handle queries and persistence. Eloquent Models represent persisted data and should remain anemic.
- Processes execute algorithms and computations without direct HTTP handling or database access. Rules contain domain policies. Data objects carry non-persisted inputs, state, or results.
- Use only the directories and components required by a module. Do not introduce unnecessary abstractions or duplicate existing functionality.
- In `Backtest`, keep `Rules`, `Process`, and `Data` separated by responsibility. Keep `Action`, `Model`, and `Repository` in their respective categories.
- Use Exact for domain types and collections, and Gauss for mathematical computation where appropriate. Check the installed versions and their actual APIs before relying on unfamiliar capabilities.

## Context-Efficient Workflow

1. Identify the owning module from the requested route or behavior. Locate only the relevant implementation files and nearby tests.
2. Read the direct callers and dependencies before changing a contract. Preserve existing behavior and module boundaries.
3. Update or add tests for changed behavior. Run the most relevant tests first. Run the full suite for cross-module refactoring or changes with a broader risk profile.
4. Run Pint against the files you changed, and report the actual commands executed and their results.
5. **Evaluate whether documentation needs updating after implementation.** If a code change affects behavior, APIs, configuration, workflows, module structure, architecture, or usage, update the relevant documentation to match the implementation.
6. If existing documentation becomes outdated, contradicts the code, or contains instructions that are no longer valid because of the task, correct the relevant sections within the scope of the change.
7. Do not modify unrelated documentation, create new documentation without a genuine need, or read every documentation file for a localized task. Read only the relevant sections.
8. If a change does not require a documentation update, do not force one.

## Verification

Use the commands relevant to the changes:

```sh
php artisan test --compact
vendor/bin/pint --test <file-or-directory>
npm run build
```

- Run the most relevant tests first, when available.
- Run the full test suite when the scope or risk of the changes warrants it.
- Run `npm run build` when frontend code is affected.
- Never claim that verification succeeded unless the command was actually executed and passed.
- Report the tests, code-quality checks, builds, and documentation updates actually completed. Mention skipped checks when relevant.

## References

- `docs/PROJECT.md` — project goals and design principles.
- `docs/coding-standar.md` — PHP, type, architecture, Exact/Gauss, and testing standards. Note that some examples in this document may still use the old path casing.

Documentation is part of code maintenance. **Keep the implementation, tests, and documentation aligned without expanding the task scope unnecessarily.**