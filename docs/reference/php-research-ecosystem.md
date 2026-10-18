# PHP Research and Production Ecosystem

This reference compares PHP libraries, native C++ components, and integration
options that may be relevant to quantitative research or production
applications. It is a technology-selection guide, not a list of Nikaido
requirements. Verify a package's current API, license, runtime requirements,
and suitability for the intended workload before adding it.

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

The options below fall into four categories:

| Category | Meaning |
|---|---|
| **Core dependency** | Directly required by Nikaido today. |
| **Optional integration** | A PHP dependency or extension that could be added for a concrete feature. |
| **Native component** | A C or C++ library or compiled module that the project would build, ship, and call from PHP (through FFI, a PHP extension, or a separate process). It is a deployment artifact, not a Composer package. |
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

When a numerical kernel is measured to be a bottleneck in pure PHP, when a
research workload (simulation, resampling, optimization, large matrix
operations) is dominated by heavy numerical loops, or when a validated C/C++
implementation already exists, see
[C++ mathematical ecosystem and PHP FFI](#c-mathematical-ecosystem-and-php-ffi-for-research-and-production)
for the native alternative and its costs.

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
external research tool, or a mature C++ numerical library behind a narrow
native interface, may be more suitable. Such a tool is not a Nikaido
capability or Composer dependency. Use a separate workflow or native component
only when the workload justifies its reproducibility and integration costs; do
not introduce a service or runtime boundary solely because another tool exists.

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
assuming that two similarly named indicators are identical. The TA-Lib C
library itself exposes a plain C API, so calling it through FFI is another
route to the same indicator set; see the
[native alternatives comparison](#native-integration-alternatives).

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

## C++ mathematical ecosystem and PHP FFI for research and production

This section covers running numerical code in C++ for **research workloads with
heavy mathematical operations** as well as for production, while keeping PHP
(in Nikaido, Laravel) responsible for validation, persistence, orchestration,
configuration, and monitoring. It assumes the PHP application layer does not
change; it describes how a native numerical core could sit underneath it, or
run beside it as a batch tool.

### When native code is justified

Native code adds a build system, an ABI boundary, a second language, and new
failure modes. It is justified by evidence, not by the word "computational":

- A profiled hot loop (indicator or signal scans, simulations, large
  matrix operations) dominates run time and cannot be fixed by an algorithmic
  change or batching in PHP.
- A research job (Monte Carlo, bootstrap, parameter sweep, optimization) runs
  for minutes or hours and is dominated by numerical loops that C++ can
  parallelize and vectorize.
- A validated C/C++ implementation already exists for a method that has no
  trustworthy PHP equivalent (a specific solver, optimizer, or special
  function).
- Control over memory layout, vectorization, or threading is required.

Keep in PHP everything that is I/O-bound, domain-validation-heavy, or changes
often. A small, stable, side-effect-free numerical kernel with a narrow
interface is the best candidate for a native boundary; business rules are not.

C++ does not make a workload faster by default. GPU-native or distributed
workloads need a GPU runtime or a compute platform, which an in-process shared
library does not provide by itself.

### C++ for research workloads

Heavy mathematical research is often a better fit for C++ than request-time
production code: jobs are batch-oriented, run from the command line, tolerate a
build step, and benefit most from parallelism and tight numerical loops.

Typical candidates:

- Monte Carlo and bootstrap resampling, and other simulations with many
  independent trials.
- Parameter sweeps and walk-forward optimization over large grids.
- Large covariance, correlation, or regression computations and other dense
  linear algebra.
- Numerical optimization, root finding, and integration used inside a larger
  research pipeline.

How it fits with PHP:

- **CLI jobs through FFI.** The default `ffi.enable=preload` already allows FFI
  in CLI scripts, so Artisan commands and queue workers can call a native
  kernel without extra configuration.
- **Standalone C++ executable.** A batch tool can read a versioned dataset and
  write results to files or a database, with PHP launching and recording the
  run. This keeps long jobs isolated from the PHP process.
- **Prototype first, port later.** Develop and validate the method in PHP with
  Gauss or in an external research tool, profile it, then port the stable
  kernel to C++ and keep the original as the reference implementation.

Research-specific requirements:

- **Reproducibility.** Use explicit seeds and a named, documented RNG engine
  (for example `std::mt19937_64` or a counter-based generator). Record the
  dataset version, compiler version and flags, and library versions with every
  result.
- **Parallel random streams.** Give each thread or trial its own independent
  stream, not a shared generator, so results do not depend on scheduling and
  draws are not correlated.
- **Deterministic reductions.** Threaded sums and BLAS operations can change
  results with thread count. Fix the thread count or use a deterministic
  reduction order when exact reruns matter.
- **Validation.** Compare against a reference implementation, known analytic
  cases, and edge cases (short series, constant inputs, zero variance) with
  explicit tolerances.
- **Method before speed.** A faster implementation of a flawed estimator or a
  leaky validation design does not improve the research; temporal splits,
  baselines, and data-leakage controls still apply.

### C++ numerical and mathematical libraries

Licenses and scope below are summarized from general knowledge of each project
and were not re-verified against current upstream releases; confirm them from
the official sources before depending on a library, especially copyleft terms.

| Area | Option | Scope | License (verify) | Trade-offs for use behind PHP |
|---|---|---|---|---|
| Linear algebra | [Eigen](https://eigen.tuxfamily.org) | Header-only matrices, vectors, decompositions, and linear solvers. | MPL-2.0 | Template-heavy; expose only concrete types (for example `double`) through a C interface. No separate library to link. |
| Linear algebra | [Armadillo](https://arma.sourceforge.net) | High-level matrix API with statistics-oriented helpers; relies on BLAS/LAPACK. | Apache-2.0 | Requires a BLAS/LAPACK backend at build and run time. |
| Arrays | [xtensor](https://github.com/xtensor-stack/xtensor) | NumPy-style n-dimensional arrays and broadcasting for C++. | BSD-3-Clause | Template-heavy; same wrapping rule as Eigen. |
| BLAS / LAPACK | [OpenBLAS](https://www.openblas.net), [reference LAPACK](https://www.netlib.org/lapack/), Intel oneMKL | Optimized dense linear algebra kernels used by many higher-level libraries. | BSD-style (OpenBLAS, LAPACK); proprietary free-use license (oneMKL) | Internal threading can oversubscribe CPUs inside PHP worker pools; cap thread counts explicitly. |
| Special functions, distributions, root finding, quadrature | [Boost.Math](https://www.boost.org/doc/libs/release/libs/math/) | Probability distributions, special functions, root finding, and numerical integration. | Boost Software License 1.0 | Header-only; large compile times; templates must be instantiated for the exposed types. |
| Arbitrary precision | Boost.Multiprecision, GMP, MPFR | Multiprecision integers, decimals, and floats with several backends. | BSL-1.0 (Boost); LGPL/GPL options (GMP, MPFR) | Needed only when `double` semantics are insufficient; much slower than hardware floats. Check LGPL obligations when distributing binaries. |
| General scientific computing | [GNU Scientific Library](https://www.gnu.org/software/gsl/) | C library for RNGs, statistics, special functions, least squares, root finding, minimization, and more. | GPL-3.0-or-later | Plain C API suits FFI well, but GPL terms may constrain how the resulting binary can be distributed. |
| Optimization | [NLopt](https://nlopt.readthedocs.io), [Ceres Solver](http://ceres-solver.org) | General nonlinear optimization (NLopt); nonlinear least squares (Ceres). | Mostly permissive; some NLopt algorithms carry other terms (verify) | Check that the chosen algorithm's license, constraint support, and convergence behavior match the objective. |
| Technical indicators | [TA-Lib C library](https://ta-lib.org) | Established catalog of technical indicators with a plain C API. | BSD-style | Callable through FFI or via the PHP extension described earlier; verify indicator defaults and warm-up conventions. |
| Quantitative finance | [QuantLib](https://www.quantlib.org) | Pricing, curves, and instruments. | BSD-style | Pricing-oriented; not a backtesting or strategy framework. Large API surface for a narrow wrapper. |
| Verification | GoogleTest or Catch2, Google Benchmark | Unit tests and micro-benchmarks for the native core. | BSD-3-Clause / Apache-2.0 | Run the native tests in CI independently of the PHP suite. |

### PHP FFI

FFI (the `ffi` extension, available since PHP 7.4) lets PHP declare C
functions and types and call them in a shared library without writing a PHP
extension. It parses **C declarations only**: it does not understand C++
headers, classes, templates, overloads, namespaces, or exceptions. A C++
library is therefore exposed through a thin `extern "C"` layer that uses plain
C types. The PHP manual has historically labeled the extension experimental;
check its current status before relying on it.

#### Availability and configuration

| Setting | Effect |
|---|---|
| `extension=ffi` | Loads the extension. It needs `libffi` on the host and, in Docker images, an installed `ffi` extension. |
| `ffi.enable=preload` (default) | FFI is usable from CLI scripts and from preloaded files only. In web SAPIs such as PHP-FPM, `FFI::cdef()` is not available during requests. |
| `ffi.enable=true` | FFI is available everywhere. This is the least restrictive and least safe value. |
| `ffi.enable=false` | FFI is disabled. |
| `ffi.preload` | Paths of header files loaded at startup with `FFI::load()`, used together with `opcache.preload` for FFI in FPM under the default restriction. |

A header intended for preloading declares its library and scope with
`#define FFI_LIB "libexample.so"` and `#define FFI_SCOPE "example"`, and is
then opened with `FFI::scope("example")`. Plan the SAPI first: CLI workers
(queue jobs, Artisan commands) work with the default setting, while FPM
requests need preloading or `ffi.enable=true`.

#### Designing the C boundary

- Export `extern "C"` functions with plain types: `double`, `int`, pointers to
  arrays plus explicit lengths, and structs of plain fields. Prefer passing
  structs by pointer rather than by value.
- Do not expose STL types, templates, or classes. Keep Eigen, Armadillo, or
  Boost objects entirely inside the C++ side.
- Never let a C++ exception cross the boundary. Catch it and return a status
  code; an uncaught exception terminates the PHP process.
- Use caller-allocated output buffers where possible so ownership stays with
  PHP. If the library allocates memory, export a matching free function.
- Return a status code for validation failures, and let PHP convert it into an
  exception, so error handling stays in the application layer.
- Version the interface (for example an exported `version()` function) so PHP
  can refuse to run against an incompatible binary.

An illustrative kernel and its PHP caller:

```cpp
// nikaido_math.cpp (illustrative)
extern "C" {

// Exponential moving average. Returns 0 on success, 1 on invalid input.
// The caller owns both buffers; `out` must hold `n` doubles.
int nm_ema(const double* values, int n, double alpha, double* out)
{
    if (!values || !out || n < 1 || !(alpha > 0.0 && alpha <= 1.0)) {
        return 1;
    }
    out[0] = values[0];
    for (int i = 1; i < n; ++i) {
        out[i] = alpha * values[i] + (1.0 - alpha) * out[i - 1];
    }
    return 0;
}

} // extern "C"
```

```bash
g++ -std=c++17 -O2 -shared -fPIC -o libnikaido_math.so nikaido_math.cpp
```

```php
<?php

$ffi = FFI::cdef(
    'int nm_ema(const double* values, int n, double alpha, double* out);',
    __DIR__.'/libnikaido_math.so',
);

$n = count($values);
$in = $ffi->new("double[{$n}]");
$out = $ffi->new("double[{$n}]");
foreach ($values as $i => $value) {
    $in[$i] = (float) $value;
}

if ($ffi->nm_ema($in, $n, 0.15, $out) !== 0) {
    throw new InvalidArgumentException('nm_ema rejected its input.');
}

$ema = [];
for ($i = 0; $i < $n; $i++) {
    $ema[] = $out[$i];
}
```

#### Data transfer and performance

- PHP arrays are not C arrays. Every call copies data into an `FFI::new()`
  buffer and back, which can cost as much as the computation for small inputs.
- Make few, coarse calls: pass a whole series or batch, not one value per
  call. Reuse buffers across calls when sizes are stable.
- Keep state inside the native side when a computation is incremental, and pass
  only new observations across the boundary.
- Measure end to end, including copying. A native kernel that wins in a C++
  micro-benchmark can lose after conversion overhead.

#### Memory and safety

- Memory created with `FFI::new()` is managed by PHP's garbage collector.
  Memory allocated by the library, or by `FFI::new()` with persistence
  disabled, must be released explicitly with the matching free function or
  `FFI::free()`.
- FFI bypasses PHP's memory safety. A wrong length, a dangling pointer, or a
  type mismatch can crash the worker or corrupt memory, not throw a PHP error.
  Validate sizes in PHP before the call and in C++ at the boundary.
- A native crash terminates the PHP process that made the call (an FPM child, a
  queue worker, or an Artisan command). Keep long or risky native work in
  queue workers rather than in interactive requests, and monitor worker
  restarts.
- `ffi.enable=true` lets any code running in PHP declare and call arbitrary
  native functions. Do not enable it where untrusted PHP code can run, and do
  not let user input select library paths or function names.

#### Numerical semantics

Moving a calculation from PHP to C++ can change its results even when the
formulas are identical:

- Gauss and BCMath-based code can use decimal arithmetic; C++ `double` is
  binary floating point. Last-digit differences can flip comparisons at
  thresholds, rounding steps, and signal conditions. Decide whether `double`,
  `long double`, or a multiprecision type is acceptable for each kernel.
- Rounding rules (half up, half even, half away from zero) differ by library.
  Reproduce the exact rule of the code being replaced.
- Avoid `-ffast-math`. It relaxes IEEE-754 behavior and can change NaN, ordering,
  and associativity results. Floating-point contraction (fused multiply-add)
  can also differ across compilers and CPU targets; fix compiler flags and
  validate on each deployment target.
- Threaded BLAS or OpenMP code can produce reduction-order differences. Set and
  document thread counts (for example `OPENBLAS_NUM_THREADS`,
  `OMP_NUM_THREADS`) when reproducibility matters.
- Keep a reference implementation (the original PHP code) and compare it with
  the native version on recorded datasets using explicit tolerances, covering
  edge cases such as zero volatility, short series, and constant inputs.

#### Build and deployment

- Build the shared library for the exact target OS, CPU architecture, and C
  library as production (for example in the same container image). A library
  built on one platform is not portable to another.
- Use `-shared -fPIC` on Linux (`.so`), produce a `.dylib` on macOS, and export
  symbols with `__declspec(dllexport)` on Windows (`.dll`). Limiting default
  symbol visibility (`-fvisibility=hidden`) keeps the exported surface to the
  intended C functions.
- Build the library in CI, run its native tests there, and ship the artifact
  with a recorded compiler version and flags. Avoid compiling on production
  hosts.
- Confirm runtime dependencies (for example `ldd libnikaido_math.so`) and that
  any BLAS/LAPACK backend is present in every environment.
- Treat an incompatible library and PHP caller as a deployment error: check an
  interface version at startup and fail clearly.

### Native integration alternatives

FFI is one of several ways to cross from PHP to native code.

| Approach | Boundary | Crash isolation | Deployment and development cost | Typical fit |
|---|---|---|---|---|
| FFI to a shared library | In-process; arrays are copied | None; a native crash ends the PHP process | Build a library per target; enable the `ffi` extension; thin C wrapper | Pure numerical functions on arrays; low call latency; no PHP extension to maintain |
| Compiled PHP extension (C or C++) | In-process; direct access to PHP values | None | Build per PHP version and thread-safety mode; higher development effort | Widely reused, stable APIs where FFI's declaration overhead and restrictions are not acceptable |
| External executable over stdin/stdout (`proc_open`) | Separate process; text or binary protocol | Strong; a crash does not take down PHP | Build one binary; no `ffi` needed; `proc_open` must be allowed; per-message serialization cost | Stateful computations kept alive across calls, or where isolation matters more than latency |
| Network or socket service (HTTP, gRPC, Unix socket) | Separate deployable | Strong | Separate deployment, monitoring, versioning, and scaling | Shared by several consumers or scaled independently of the PHP application |

### Decision guide for research and production

| Requirement | Direction to evaluate | Main consideration |
|---|---|---|
| Hot, side-effect-free numerical kernel measured as a bottleneck in PHP | C++ implementation behind `extern "C"`, called through FFI | Profile first; account for copy cost, `double` versus decimal semantics, and the build and deployment pipeline. |
| Heavy research computation (Monte Carlo, bootstrap, parameter sweeps, large matrix or optimization workloads) | C++ kernel called through FFI from CLI jobs, or a standalone C++ executable that writes versioned results | Fix seeds, RNG engine, compiler flags, thread counts, and library versions; validate against a reference implementation. |
| Dense linear algebra, decompositions, or solvers beyond what PHP libraries offer | Eigen or Armadillo with an optimized BLAS/LAPACK backend | Wrap concrete types only; control thread counts; verify the license of each backend. |
| Established indicator catalog | TA-Lib: PHP extension or direct C API through FFI | Verify indicator conventions and choose one native integration route per environment. |
| Optimization or nonlinear least squares | NLopt or Ceres | Match the algorithm, constraints, and convergence criteria; check algorithm-level licenses. |
| Strong fault isolation or a stateful native computation | External executable over `proc_open`, or a service | Define the message protocol, lifecycle, and error reporting; accept serialization overhead. |
| Several consumers or independent scaling | Network service | Operate it as a separate component with its own deployment and monitoring. |
| GPU-oriented ML, specialized numerical workloads, or distributed analysis | Specialized external runtime or compute platform | An in-process FFI library does not by itself provide GPU or distributed execution; keep PHP responsible for workflow, persistence, and monitoring. |
| Small, changing business logic or I/O-bound work | Stay in PHP | A native boundary adds cost without a numerical payoff. |

## Practical decision guide for Nikaido extensions

| Requirement | Start by evaluating | Important check |
|---|---|---|
| General PHP mathematical or statistical operation | Gauss, then MathPHP if a needed implementation is missing | Compare the actual API, assumptions, precision, edge cases, and tests; avoid overlapping dependencies without a benefit. |
| Explicit arbitrary-precision integers, decimals, or rationals | Brick\Math | Decide whether decimal/rational arithmetic is required; it is not a statistical package. |
| Established catalog of technical indicators | TA-Lib PHP extension, or the TA-Lib C library through FFI | Verify indicator semantics and the native build/deployment path. |
| Small number of indicators already represented in project dependencies | Gauss or a narrowly scoped implementation | Test warm-up and output conventions; do not duplicate an existing suitable algorithm. |
| Traditional ML workflow in PHP | Rubix ML | Confirm the exact algorithm and evaluation APIs, and account for PHP `>=8.3`. |
| Performance-critical or research-heavy numerical kernel | C++ behind a C interface, called through FFI or run as a batch executable | Measure first; compare native and PHP results on recorded data with explicit tolerances; plan builds per target. |
| Advanced statistical, GPU, or high-volume numerical workload | External research/compute tools, or a C++ library where an in-process CPU kernel suffices | Include integration and operational cost in the decision; keep PHP integration bounded and justified. |
| CSV processing | League CSV | Parsing a file does not implement a Nikaido import workflow or domain validation. |
| Application persistence | Existing Laravel/Eloquent and configured database | Separate transactional persistence from large-scale analytical storage. |

## Sources and selection checklist

The links above point to official project documentation/repositories and
Packagist metadata where available. Before adding a package or native
component:

1. Verify its current PHP and extension requirements against the project's
   supported runtime and deployment environment.
2. Verify license compatibility, release/support status, API behavior, and
   maintenance signals from the official source. Pay particular attention to
   copyleft licenses on libraries that would be linked into a shipped binary.
3. Check whether Nikaido or an existing dependency already provides the
   required operation.
4. Confirm the method's assumptions, numerical behavior, edge cases, and
   testability against the research requirement.
5. Account for deployment complexity, data flow, performance measurement, and
   operational ownership—not just Composer installation.
6. For native code, confirm the build toolchain, target platform and CPU
   architecture, how the artifact is built and versioned in CI, and the chosen
   `ffi.enable` mode for each PHP SAPI.
7. For native code, compare the result against a reference implementation on
   recorded data, define tolerances, and test crash and invalid-input behavior
   at the boundary.
8. Add an optional dependency only when the concrete feature justifies its
   maintenance and runtime cost. Recommended packages are not automatically
   supported Nikaido capabilities.