# PHP Research and Production Ecosystem

This reference compares PHP libraries and frameworks that may be relevant to
quantitative research or production applications. It is a technology-selection
guide, not a list of Nikaido requirements. Verify a package's current API,
license, runtime requirements, and suitability for the intended workload before
adding it.

**Information last verified: 2026-10-17.** Versions and support policies can
change; the linked project documentation and package metadata are authoritative.

For Nikaido's goals and current technology choices, see the
[project overview](../PROJECT.md). The
[architecture overview](../architecture/overview.md) and
[coding standards](../coding-standar.md) describe project boundaries and
existing conventions. For supported market-data workflows, see
[data ingestion and validation](../features/data-ingestion-and-validation.md);
installation and runtime requirements are in the
[installation guide](../getting-started/installation.md).

## How to read these recommendations

Nikaido's root [`composer.json`](../../composer.json) requires PHP `^8.2`,
Laravel `^12.0`, Exact `^2.0`, and Gauss `^0.3.0`. Exact is used for domain
types and collections; Gauss is used for numerical work. These are current
project dependencies, not a blanket endorsement of every capability in either
library. `composer.lock` also contains `brick/math`, but it is not a direct
root requirement. Do not rely on a transitive package as if it were a declared
application dependency.

The options below fall into three categories:

| Category | Meaning |
|---|---|
| **Core dependency** | Directly required by Nikaido today. |
| **Optional integration** | A PHP dependency or extension that could be added for a concrete feature. |
| **External research tool** | A separate process or environment that need not become a Nikaido dependency. |

The project currently has no built-in general CSV/Excel upload. See its
[ingestion limitations](../features/data-ingestion-and-validation.md) before
choosing a file-processing package.

## Mathematical, numerical, and statistical computing

These areas overlap but are not interchangeable:

- **Mathematical utilities** provide reusable operations and types.
- **Arbitrary-precision arithmetic** represents numbers beyond ordinary
  floating-point precision; it does not by itself supply statistical methods.
- **Numerical algorithms** approximate solutions using finite-precision
  computation and may need explicit tolerances and convergence criteria.
- **Scientific computing** combines numerical methods, data structures, and
  domain algorithms; a general mathematics package does not imply broad
  scientific-computing coverage or performance.
- **Linear algebra and optimization** are distinct capabilities. Matrix
  operations do not automatically provide a suitable optimizer, and an
  optimizer must match the objective, constraints, and convergence needs.

| Option | Purpose and verified scope | Requirements and trade-offs | Appropriate use |
|---|---|---|---|
| [Gauss](https://github.com/pwaskito611/gauss) ([Packagist](https://packagist.org/packages/pandu/gauss)) | Nikaido's **core dependency**. A composable toolkit whose documented areas include decimal and floating-point computation, algebra, probability, statistics, linear algebra, numerical methods, optimization, and time series. MIT; Composer declares PHP `^8.2`. Latest listed release at verification: `v0.3.1` (pre-1.0). | `composer require pandu/gauss`. Its README describes BCMath as the default decimal backend and native floating-point as an option, but Composer does not declare `ext-bcmath`; verify the chosen calculation path and target PHP extensions. As a pre-1.0 package, verify APIs against the installed version. | Prefer when the required operation exists in the installed Gauss version and integrates naturally with Nikaido's numerical code. The project already uses its `Number`, `Statistics`, and time-series APIs. |
| [MathPHP](https://github.com/markrogoyski/math-php) ([Packagist](https://packagist.org/packages/markrogoyski/math-php)) | General-purpose pure-PHP mathematics. Documented coverage includes statistics, regression, probability distributions, matrices/vectors, numerical analysis, interpolation, differentiation, integration, and root finding. MIT; declares PHP `>=7.2.0` and `ext-json`. Latest listed stable release: `v2.13.0` (2025-10-24); repository/package metadata showed later development activity. | `composer require markrogoyski/math-php`. Capabilities overlap with Gauss; adding it increases dependency surface. Its broad API does not make every method appropriate for financial or time-series inference. | Consider when it offers a mature, tested implementation absent from the installed Gauss API and replacing or complementing it has a clear benefit. Compare exact method semantics, input types, and numerical behavior before choosing. |
| [Brick\Math](https://github.com/brick/math) ([Packagist](https://packagist.org/packages/brick/math)) | Arbitrary-precision `BigInteger`, `BigDecimal`, and `BigRational` arithmetic. MIT; declares PHP `^8.2`. At verification the `1.0.0` stable release was listed (2026-09-12). | `composer require brick/math`. GMP and BCMath are optional accelerators; the library documents a pure-PHP fallback. This is number arithmetic, not a statistics or scientific-computing suite. | Use when explicit arbitrary-precision integer, decimal, or rational values are the requirement. Choose decimal versus binary floating-point intentionally; higher precision is not automatically more accurate for every algorithm. |

### Statistics and probability

Gauss and MathPHP both document statistical and probability functionality.
Before adding a second library, compare the specific estimator or distribution
needed, its assumptions, missing-data behavior, numerical stability, and test
coverage. Gauss is the natural first place to check in Nikaido because it is
already a core dependency; MathPHP may be preferable for an existing,
well-tested implementation not available there. Neither library should be
assumed to provide every inferential procedure, econometric model, or
time-series method.

For advanced statistical modeling, hypothesis-testing workflows, or
specialized econometrics not available in the verified PHP APIs, a specialized
external research tool may be more suitable. Such a tool is not a Nikaido
capability or Composer dependency. Use a separate workflow only when the
workload justifies its reproducibility and integration costs; do not introduce
a service or runtime boundary solely because another tool exists.

## Machine learning

| Option | Verified scope | Requirements and trade-offs | Appropriate use |
|---|---|---|---|
| [Rubix ML](https://github.com/RubixML/ML) ([documentation](https://rubixml.github.io/ML/latest/), [Packagist](https://packagist.org/packages/rubix/ml)) | PHP machine-learning toolkit. Package metadata lists classification, regression, clustering, anomaly detection, feature selection/extraction, NLP, and ETL. MIT; the verified stable `3.2.2` release (2026-10-17) requires PHP `>=8.3`. | `composer require rubix/ml` requires upgrading the runtime floor if the application must continue supporting PHP 8.2. `ext-tensor`, `ext-gd`, `ext-mbstring`, `ext-svm`, and `ext-swoole` are suggestions for particular functionality or acceleration, not universal requirements. Confirm a needed algorithm and its evaluator in the official docs before adopting it. | Suitable when a supported PHP-native model and its training/inference workflow meet the specific use case, and the PHP version and operational requirements fit the application. |

Statistical estimation, numerical computation, and machine learning are related
but distinct: a regression routine is not automatically a complete model
training, validation, and deployment pipeline. Evaluate data leakage controls,
temporal splits, baseline comparisons, metrics, and reproducibility for the
actual research question. Do not infer support for every ML algorithm from
Rubix ML's general toolkit description.

## Technical indicators and market analysis

| Option | Capabilities and requirements | Trade-offs |
|---|---|---|
| [TA-Lib PHP extension](https://github.com/TA-Lib/ext-ta-lib) | PHP extension exposing the TA-Lib C library. Its Composer metadata declares MIT, PHP `>=8.1`, and extension name `ta_lib`. The documented installation uses PIE with TA-Lib support or a native source build involving `phpize` and `configure`. | This is not a normal pure-PHP Composer-only install. Extension builds must be available and consistent in development, CI, and production containers/hosts. Verify the desired functions and their exact input/output semantics in TA-Lib documentation. |
| [LupeCode phpTraderNative](https://github.com/LupeCode/phpTraderNative) ([Packagist](https://packagist.org/packages/lupecode/php-trader-native)) | Pure-PHP Trader-extension polyfill; declares PHP `>=8.2` and GPL-3.0-or-later. Its README reports no runtime extension requirement. | Check license compatibility carefully. The project documents differences in some defaults from PECL Trader and reports it is slower than the C extension; treat performance numbers as maintainer-reported, not independent benchmarks. Latest listed release at verification: `v2.2.0` (2025-02-04). |
| Gauss or a project-specific implementation | Gauss contains time-series smoothing functionality; simple indicators may also be implemented directly when that remains small and well-tested. | Confirm that indicator definitions, warm-up periods, missing values, edge cases, and output alignment match the intended convention. Avoid reimplementing a broad indicator suite when a verified implementation is more appropriate. |

TA-Lib is attractive when its established indicator set and C-backed
integration justify the additional extension build and deployment burden.
For a small number of indicators already covered by Gauss, introducing a native
extension may be unnecessary. Compare exact formulas and defaults rather than
assuming that two similarly named indicators are identical.

## Time series, data processing, and research utilities

| Need | Direction | Considerations |
|---|---|---|
| Time-series smoothing and numerical operations | Start by checking Gauss; use a focused PHP implementation only for a narrow, verified need. | Check the installed API, ordering, warm-up, missing observations, precision, and determinism. Gauss advertises time-series and optimization areas, but do not assume a package provides every model or algorithm. |
| CSV reading, writing, and transformation | [League CSV](https://csv.thephpleague.com/) ([repository](https://github.com/thephpleague/csv), [Packagist](https://packagist.org/packages/league/csv)) is a PHP CSV-focused option with reading, writing, importing/exporting, filtering, and transformation features. MIT; the current metadata at verification requires PHP `^8.2.0` and `ext-filter`. | `composer require league/csv`. Extensions such as DOM, iconv, and mbstring are suggested for particular converters or integrations. This does not add a Nikaido upload workflow by itself; validation, duplicate policy, batching, and transaction behavior still need to be designed. |
| Excel spreadsheets | Select a spreadsheet reader/writer only if an Excel use case is required. | A spreadsheet package is not needed for CSV or for the currently supported candle ingestion paths. Evaluate memory use and file-size limits for actual datasets before adding one. |
| Random sampling and simulation | Use Gauss where its documented API fits; Nikaido already contains project-specific Monte Carlo analysis. | Simulation design, seeding, sampling assumptions, and result interpretation matter more than simply selecting a random-number API. See [robustness analysis](../features/robustness-analysis.md) and [Monte Carlo](../features/monte-carlo.md). |
| Parallel or distributed compute | Begin with the application's existing Laravel queue infrastructure for bounded background jobs; see [concurrency](../features/concurrency.md). | Queueing work is not the same as vectorized numerical computing or a distributed analytics engine. Profile and define workload and failure requirements before introducing workers, a broker, or an external compute platform. |
| Database abstraction | Laravel Eloquent and the configured relational database are Nikaido's application persistence path. [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html) ([Packagist](https://packagist.org/packages/doctrine/dbal)) is an MIT-licensed SQL abstraction and schema-introspection library; the verified `4.5.0` release was published 2026-09-26 and the development `4.6` line declares PHP `^8.2`. | DBAL is not a replacement analytical database or an automatic reason to use Doctrine alongside Eloquent. Add it when its abstraction or schema tools address a real requirement; a connection still needs the target database driver. |

### Application persistence versus analytical storage

Use the relational database selected by the application for transactional
records and workflows. For exploratory batch analysis, consider extracting a
reproducible, versioned dataset into a format and tool suited to the expected
volume; avoid routing large analytical scans through interactive application
queries without measuring the impact. High-volume columnar analytics, GPU
computing, and distributed numerical workloads may be better handled by
specialized external tools. The storage and process boundary should follow
measured requirements, not a presumption that every system needs a data
warehouse or microservice.

Nikaido's current ingestion behavior and limitations are documented in
[data ingestion and validation](../features/data-ingestion-and-validation.md)
and [data management](../user-guide/data-management.md). A library that can
parse a file does not imply that Nikaido provides a file-import feature.

## Production PHP frameworks

PHP is a viable production choice for many business applications, research
workbenches, APIs, administrative interfaces, and background-processing
workloads. Choose based on the application and team requirements; no framework
is universally best. Frameworks provide application structure and runtime
integration, whereas libraries such as Gauss, Brick\Math, and Rubix ML solve
different problems.

| Framework | Type and fit | Routing, DI, and persistence | Authentication, jobs, scheduling, testing, and operations |
|---|---|---|---|
| [Laravel](https://laravel.com/docs) ([repository](https://github.com/laravel/framework)) | Full-stack framework with an integrated ecosystem. Nikaido uses Laravel 12. Laravel 13, the current major at verification, requires PHP `^8.3`; the current Nikaido floor is PHP `^8.2`, so do not infer that Laravel 13 is a drop-in upgrade. MIT. | Integrated routing, middleware, service container, Eloquent ORM, and migrations. | Integrated authentication options and authorization policies; queues and scheduling are first-party framework capabilities. Strong framework testing integration. Deploy with PHP runtime, web server/process management, configured database and any required queue/cache services. |
| [Symfony](https://symfony.com/doc/current/index.html) ([releases](https://symfony.com/releases), [repository](https://github.com/symfony/symfony)) | Full-stack framework and reusable components; suitable when explicit composition and component reuse fit the application. MIT. Symfony 8.1.9 was current stable in October 2026 and requires PHP `>=8.4.1`; Symfony 7.4 is the current LTS line. | Component-based routing, middleware/event infrastructure, and dependency injection. Doctrine integration is commonly used but separately selected; Symfony is not inseparable from one ORM. | Security, Messenger, and Scheduler provide framework ecosystem options; test tooling is mature. Component choice gives flexibility but requires deliberate integration/configuration; plan runtime and operational services from the selected components. |
| [Slim](https://www.slimframework.com/docs/v4/) ([repository](https://github.com/slimphp/Slim)) | Lightweight HTTP microframework for APIs and small HTTP applications. Slim 4.15.3 was the latest stable release found at verification; MIT; declares PHP 7.4 and PHP 8.0–8.5. | Minimal router/dispatcher and middleware pipeline. A PSR-7 implementation is a separate requirement; choose DI and persistence components as needed. | Authentication, queues, scheduling, and persistence are composed from other packages or infrastructure. Slim keeps the framework small but transfers more integration and operational choices to the application team. |
| [Mezzio](https://docs.mezzio.dev/mezzio/) ([repository](https://github.com/mezzio/mezzio)) | PSR-15 middleware microframework for middleware-oriented applications. Mezzio 3.28.1 was the stable release listed at verification; BSD-3-Clause; declares PHP 8.2–8.5. | Middleware pipeline. Manual installation requires a router and DI container; an application also needs a PSR-7 implementation. The official skeleton/installer simplifies initial composition. | Compose authentication, persistence, queues, and scheduling from compatible packages. Suits teams wanting explicit middleware composition; more integration decisions remain application-owned than in a full-stack framework. |

Framework versions move on independent schedules. Confirm supported PHP
versions, maintenance policy, extension requirements, and dependency
compatibility from the official release/support pages before selecting a
version. The versions above are point-in-time observations, not a promise of
future support.

### Production decision guide

| Requirement | Direction to evaluate | Main consideration |
|---|---|---|
| Integrated web application with ORM, queues, migrations, and a broad ecosystem | Laravel | Match the Laravel major to the PHP floor and existing project conventions. |
| Component reuse, explicit integration, or an established Symfony organization | Symfony | Choose compatible components and a supported PHP/framework release line. |
| Small API where a minimal HTTP core is desirable | Slim | Select and maintain the PSR-7 implementation, DI, persistence, and background-work integrations. |
| Middleware-first PSR-15 application | Mezzio | Plan router, container, PSR-7 implementation, and operational components. |
| GPU-oriented ML, specialized numerical workloads, or highly vectorized analysis | Specialized external runtime, potentially called by PHP | Keep PHP responsible for workflows, configuration, persistence, orchestration, and monitoring when useful; add a process boundary only for a defined workload and interface. |

PHP may be a poor fit when a workload requires capabilities that are not well supported by its ecosystem, such as specialized numerical algorithms, GPU-native computation, distributed execution, or low-level control over memory and hardware. In such cases, choose a runtime or language based on the specific requirements, available libraries, integration costs, deployment constraints, and maintainability rather than assuming that a different language will automatically provide better performance. C++ may be appropriate when direct hardware access, specialized numerical libraries, or fine-grained control over memory and execution are essential, but it should not be the default recommendation solely because a workload is computationally intensive.

## Practical decision guide for Nikaido extensions

| Requirement | Start by evaluating | Important check |
|---|---|---|
| General PHP mathematical or statistical operation | Gauss, then MathPHP if a needed implementation is missing | Compare the actual API, assumptions, precision, edge cases, and tests; avoid overlapping dependencies without a benefit. |
| Explicit arbitrary-precision integers, decimals, or rationals | Brick\Math | Decide whether decimal/rational arithmetic is required; it is not a statistical package. |
| Established catalog of technical indicators | TA-Lib PHP extension | Verify indicator semantics and the native extension build/deployment path. |
| Small number of indicators already represented in project dependencies | Gauss or a narrowly scoped implementation | Test warm-up and output conventions; do not duplicate an existing suitable algorithm. |
| Traditional ML workflow in PHP | Rubix ML | Confirm the exact algorithm and evaluation APIs, and account for PHP `>=8.3`. |
| Advanced statistical, GPU, or high-volume numerical workload | External research/compute tools | Include integration and operational cost in the decision; keep PHP integration bounded and justified. |
| CSV processing | League CSV | Parsing a file does not implement a Nikaido import workflow or domain validation. |
| Application persistence | Existing Laravel/Eloquent and configured database | Separate transactional persistence from large-scale analytical storage. |

## Sources and selection checklist

The links above point to official project documentation/repositories and
Packagist metadata where available. Before adding a package:

1. Verify its current PHP and extension requirements against the project's
   supported runtime and deployment environment.
2. Verify license compatibility, release/support status, API behavior, and
   maintenance signals from the official source.
3. Check whether Nikaido or an existing dependency already provides the
   required operation.
4. Confirm the method's assumptions, numerical behavior, edge cases, and
   testability against the research requirement.
5. Account for deployment complexity, data flow, performance measurement, and
   operational ownership—not just Composer installation.
6. Add an optional dependency only when the concrete feature justifies its
   maintenance and runtime cost. Recommended packages are not automatically
   supported Nikaido capabilities.
