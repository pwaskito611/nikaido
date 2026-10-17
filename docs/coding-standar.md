# Coding Standards — PHP 8.2+ with Exact and Gauss

## 1. Purpose

These standards apply to all PHP development within the project.

- **Exact (`pandu/exact`)** — domain type modeling, result representation, pattern matching, and immutable collections.
- **Gauss (`pandu/gauss`)** — mathematical operations, statistics, probability, numerical analysis, and relevant mathematical computation.
- **Feature-Based Architecture** — organizes application code by capability under `app/Feature/`.
- **Core Architecture** — Controller, Action, Repository, and Model form the architectural foundation of each feature.
- **Optional Components** — Data, Config, Process, and Rules are additional components introduced when needed.
- **Anemic Model** — models represent persisted data without substantial business logic.
- **PHPUnit** — verifies behavior, integration, and regression.

Core principles:

- Correctness takes priority over implementation convenience.
- Type contracts must be explicit.
- Every component must have a clear responsibility.
- Each feature must have a consistent internal structure without arbitrary structural anomalies.
- Different features may have different structures according to their responsibilities and complexity.
- Consistency does not require every feature to have identical directories or classes.
- Abstractions must serve a real need.
- Avoid God Functions, God Methods, God Classes, duplicated logic, and circular dependencies.
- Do not introduce separate Service or Use Case layers.
- Not every operation requires every component type.
- Do not add layers merely to satisfy an architectural pattern.

## 2. Feature-Based Architecture

All application-specific feature code belongs under `app/Feature/`.

Features are organized by application capability, not by file type globally.

Example structure:

```text
app/
└── Feature/
    ├── MarketTypes/
    │   ├── Controller/
    │   ├── Action/
    │   ├── Repository/
    │   ├── Model/
    │   └── Tests/
    │       ├── Unit/
    │       └── Integration/
    │
    ├── MarketExploration/
    │   ├── Controller/
    │   ├── Action/
    │   ├── Repository/
    │   ├── Model/
    │   ├── Rules/
    │   ├── Config/
    │   └── Tests/
    │       ├── Unit/
    │       └── Integration/
    │
    └── Backtest/
        ├── Controller/
        ├── Action/
        ├── Repository/
        ├── Model/
        ├── Data/
        ├── Rules/
        ├── Process/
        ├── Config/
        └── Tests/
            ├── Unit/
            └── Integration/
```

This structure is illustrative. Additional directories should be introduced only when a real responsibility requires them.

### 2.1 Core Components

Every feature follows the same architectural foundation:

- **Controller** — handles the feature's transport boundary or external entry point.
- **Action** — represents and orchestrates an application operation.
- **Repository** — handles data access and persistence.
- **Model** — represents persisted data, primarily through Eloquent models.

These responsibilities are shared across features. Their implementations and class counts may differ, but their architectural meanings must remain consistent.

The presence of four core components in this standard does not require every operation to pass through all four. For example, an Action that performs computation without reading or persisting data does not need to call a Repository.

### 2.2 Optional Components

The following components should be introduced only when needed:

- **Data** — represents input, output, or intermediate data that benefits from an explicit structure.
- **Config** — holds parameters and settings that should be separated from implementation.
- **Process** — executes a task or capability with a clearly defined responsibility.
- **Rules** — isolates meaningful domain rules, criteria, constraints, or policies.

There is no requirement to create all four directories in every feature.

Do not create empty directories, placeholder classes, or abstractions without real functionality merely to standardize the structure.

### 2.3 Cross-Feature Consistency

The following rules must be maintained:

- All features use the same definitions of Controller, Action, Repository, and Model responsibilities.
- When a feature uses optional components, those components must follow the applicable naming, placement, and responsibility conventions.
- Classes with the same responsibility must not be placed inconsistently within the same feature without a clear architectural reason.
- Do not mix Rules with Repository responsibilities, Config with algorithms, or Controllers with application orchestration.
- Avoid arbitrary internal structural variations, such as placing some Process classes in the feature root and others under `Process/`.
- If a feature requires an additional subdirectory, apply the pattern consistently to components with the same category and responsibility.
- Structural differences between features must be justified by differences in responsibility or complexity, not inconsistent implementation preferences.

### 2.4 Cross-Feature Dependencies

- Each feature owns its internal implementation.
- Other features must not depend arbitrarily on internal details they do not own.
- Use clear entry points or public contracts when another feature needs an operation.
- Orchestration involving multiple capabilities belongs in the calling feature's Action or in a dedicated orchestration feature that genuinely owns that responsibility.
- Avoid circular dependencies between features.
- Do not duplicate business logic merely to avoid cross-feature dependencies.
- Do not create global layers for Action, Repository, Process, or Rules when their responsibilities belong to individual features.

Example namespaces:

```php
App\Feature\MarketTypes\Controller\MarketTypeController
App\Feature\MarketTypes\Action\CreateMarketTypeAction
App\Feature\MarketTypes\Repository\MarketTypeRepository

App\Feature\MarketExploration\Rules\ExplorationRules

App\Feature\Backtest\Data\BacktestInput
App\Feature\Backtest\Process\ExecuteStrategyProcess
```

`app/Feature/` is a project convention, not a directory structure required by Laravel. Preserve the capitalization and namespace conventions established by the project.

## 3. Core Architecture

### 3.1 Controller — Transport Boundary

A Controller receives requests at the transport boundary and delegates operations to Actions.

Responsibilities:

- Receive HTTP requests.
- Use FormRequest for transport-level validation when appropriate.
- Retrieve validated input.
- Convert input into an Action input contract when necessary.
- Invoke the relevant Action.
- Construct an HTTP response based on the Action result.

Restrictions:

- Do not implement substantial business logic.
- Do not orchestrate Process components directly.
- Do not execute domain algorithms or complex computations.
- Do not write database queries.
- Do not access Repositories directly.
- Do not handle internal application process details that belong to an Action.

Controllers should remain thin: accept input, invoke an Action, and return a response.

### 3.2 Action — Application Orchestration

An Action represents one application operation with a clear purpose.

Examples:

- `CreateMarketTypeAction`
- `RunBacktestAction`
- `GeneratePerformanceReportAction`

Responsibilities:

- Provide the application's operation entry point through `execute()`.
- Determine the invocation order of required components.
- Manage success paths, orchestration branches, and failure handling.
- Pass inputs and results between stages.
- Determine when data must be read or persisted.
- Produce output according to the application contract.

Rules:

- Each Action must have one clear application-level purpose.
- `execute()` should expose the main workflow rather than every implementation detail.
- An Action may invoke Rules, Process, Repository, or other components as needed.
- An Action may perform simple tasks directly.
- An Action must not write database queries directly.
- An Action must not become the primary location for complex domain algorithms.
- An Action must not duplicate rules already owned by Rules or Process.
- Failures at critical stages must be handled before subsequent stages execute.
- Do not introduce separate Service or Use Case layers.
- Do not create base Actions, interfaces, or generic handlers without a concrete need.

An Action may contain private methods for orchestration or result mapping, provided its overall responsibility remains coherent.

### 3.3 Repository — Data Access

A Repository handles data access and persistence.

Responsibilities:

- Execute database queries.
- Read, create, update, and delete data.
- Use Eloquent or the project's established persistence mechanism.
- Map persisted results into types required by callers.
- Convert between internal representations and storage formats.

Rules:

- A Repository must not orchestrate an entire application operation.
- A Repository must not become the home of substantial business logic or domain algorithms.
- Do not return raw query builders when the contract should return data.
- Do not create Repository interfaces automatically.
- Separate Repositories according to genuine data-access responsibilities, not method count.
- Use explicit result types for single values, optional values, collections, or failures.
- A Repository may be called by an Action or Process when appropriate.
- Do not artificially relocate responsibilities merely to enforce a particular invocation pattern.

### 3.4 Model — Anemic Model

A Model represents persisted data, primarily through Eloquent.

Examples:

- `MarketPrice`
- `BacktestRecord`
- `TradingConfiguration`

Rules:

- Models focus on attributes, casts, relationships, and technical behavior required by Eloquent.
- Substantial business logic belongs in Rules or Process according to its responsibility.
- Application orchestration belongs in Actions.
- Persistence access follows the project's Repository conventions.
- A Model must not orchestrate the entire application workflow.
- A Model is not required to store attributes using Exact types.

Do not add complex behavior to a Model merely because Eloquent provides a place for it.

## 4. Optional Components

### 4.1 Data — Data Representation

Data represents information consumed or produced by an operation.

Examples:

- `RunBacktestInput`
- `BacktestResult`
- `MarketCandle`
- `PerformanceMetrics`

Responsibilities:

- Define input, output, or intermediate data structures.
- Establish explicit data contracts.
- Reduce reliance on associative arrays with unclear structures.
- Use Exact types when appropriate for internal contracts.

Rules:

- Data must not orchestrate application operations.
- Data must not execute database queries.
- Data must not contain substantial business logic.
- Data does not always need to be implemented as a DTO class.
- Do not create Data classes merely to wrap simple values without a clear benefit.
- Data does not replace Models or Repositories.

Responsibility boundaries:

- Data represents information an operation requires or produces.
- Model represents persisted data.
- Repository handles how data is read and stored.

A separate Data component may be unnecessary when an existing PHP or Exact type already expresses the contract clearly.

### 4.2 Config — Configuration

Config provides parameters that control feature behavior.

Examples:

- Default market exploration parameters.
- Backtest simulation limits and options.
- Data-source settings.
- Configurable process behavior that should not require changing algorithms.

Rules:

- Config contains parameters, options, and configuration values.
- Config must not contain business logic.
- Config must not execute database queries.
- Config must not orchestrate application operations.
- Do not mechanically move every literal into Config.
- Distinguish configurable values from internal constants that do not need configuration.
- Validate parameters at the appropriate transport or domain boundary.

Use Config when there is a genuine configuration requirement. Not every feature needs a Config directory.

### 4.3 Rules — Domain Rules

Rules contain meaningful domain rules, criteria, constraints, or policies.

Examples:

- Trading configuration validation criteria.
- Market selection criteria.
- Simulation parameter validity constraints.
- Rules for accepting or rejecting domain conditions.

Rules:

- Focus on decisions or constraints applied to inputs.
- Must not handle HTTP.
- Must not write database queries.
- Must not orchestrate the entire application operation.
- Must not serve as configuration storage.
- May use Data or Exact types as needed.
- May be used by Actions or Process when consistent with the feature's responsibilities.
- Simple rules may remain inside an Action or Process when extracting them would not provide meaningful benefits.

Separate Rules when a rule has clear domain meaning, meaningful complexity, a need for independent testing, or multiple internal consumers.

### 4.4 Process — Individual Operation

Process executes a specific task or capability as part of an application operation.

Examples:

- `LoadMarketDataProcess`
- `ExecuteStrategyProcess`
- `SimulateOrderExecutionProcess`
- `CalculatePerformanceProcess`

Responsibilities:

- Execute a specific task with an explainable purpose.
- Perform relevant algorithms, transformations, or computational stages.
- Use Rules, Data, Exact, Gauss, or Repository as appropriate.
- Produce output with an explicit contract.
- Separate meaningful implementation details from Action orchestration.

Rules:

- Process may contain private methods for internal implementation.
- Process may use Repository when data access is part of its responsibility.
- Process may invoke another Process when the dependency is genuine and does not create a cycle.
- Process must not handle HTTP.
- Process must not take over orchestration of the entire application operation.
- Do not create Process classes merely to wrap a single method call without meaningful benefit.
- Do not create a Process for every trivial mechanical step.
- Do not combine unrelated capabilities into a God Class.

**Not every feature needs Process. Not every Action requires a separate Process.** An Action may execute a simple operation directly. Introduce Process when the task has sufficient complexity, an independent responsibility, or testing and reuse needs to justify the separation.

## 5. Component Selection and Structural Consistency

Choose components according to responsibility, not a requirement to create every directory.

| Requirement | Component |
|---|---|
| Handle HTTP requests and responses | Controller |
| Orchestrate an application operation | Action |
| Read or persist data | Repository |
| Represent persisted data | Model |
| Carry input, output, or intermediate data contracts | Data |
| Provide configurable parameters | Config |
| Express domain rules and criteria | Rules |
| Execute a specific task or capability | Process |

### 5.1 Consistency Within a Feature

Every feature must be internally consistent.

- Follow consistent directory and namespace conventions.
- Place components according to their responsibilities.
- Do not scatter classes with the same responsibility across different locations without a clear reason.
- Apply consistent naming conventions to classes and methods with similar roles.
- Maintain the same responsibility boundaries throughout the feature.
- Do not introduce local exceptions without an explainable architectural reason.
- When changing a feature's internal structure, review the feature for conflicting legacy patterns.
- Ensure new implementations follow the feature's established internal conventions.

### 5.2 Variation Between Features

Different features may have different structures.

Examples:

- Market Types may use only Controller, Action, Repository, and Model.
- Market Exploration may add Rules and Config.
- Backtest may use all components, including Data and Process.

These differences are acceptable provided that:

1. Core component definitions and responsibilities remain consistent.
2. Additional components serve genuine purposes.
3. Each feature remains internally consistent.
4. Unnecessary business logic duplication is avoided.
5. Dependencies remain clear and acyclic.

Do not force a simple feature to adopt the structure of a complex feature. Conversely, do not retain an overly simple structure when genuine complexity requires separation of responsibilities.

### 5.3 Refactoring Decisions

When changing structure:

- Do not refactor other features merely to make their directory counts identical.
- If a pattern is needed across multiple features, consider extracting a shared component only when it has a clear shared responsibility.
- If an internal anomaly is found, correct it according to responsibility boundaries and feature conventions.
- Do not move classes merely to make the structure look more symmetrical.
- Avoid cross-feature refactoring unrelated to the current change.
- Check the impact on namespaces, autoloading, routing, public contracts, and tests.

## 6. Execution Flow

A typical HTTP workflow:

```text
HTTP Request
    |
    v
Controller
    |
    v
Action
    |
    +----> Rules
    |
    +----> Process ----> Data
    |         |
    |         +-------> Repository ----> Database
    |
    +----> Repository (simple orchestration-level access)
    |
    v
Action Output
    |
    v
Controller
    |
    v
HTTP Response
```

This diagram is conceptual. Rules, Process, and Data are optional. Not every operation must pass through every component.

Workflow rules:

- Controller passes input to an Action and constructs the response.
- Action orchestrates the application operation.
- Rules express relevant rules or criteria.
- Process executes a specific task.
- Data carries information through explicit contracts.
- Repository handles persistence access.
- Model represents persisted data.
- An Action or Process may operate without a Repository when persistence is unnecessary.
- Config supplies parameters when required.
- Failure handling must follow component contracts and prevent subsequent operations from executing incorrectly.

## 7. Exact — Internal Type and Data Modeling

Exact (`pandu/exact`) is used for internal type and data modeling when appropriate.

Rules:

- Use `Option` for optional values with domain meaning.
- Use `Either` for results that explicitly distinguish success from failure.
- Use `Validated` when validation needs to accumulate errors.
- Use `Variant` and `PatternMatch` to model and handle alternative domain states.
- Use immutable collections such as `Vec`, `Map`, and `Set` according to their semantics.
- Use non-empty collections only when their invariants are guaranteed.
- Use Exact types in internal Action, Rules, Process, Data, and Repository contracts when appropriate for the component and supported API.
- Do not mechanically use Exact for every scalar or object.
- Avoid repeated conversions between Exact types and native PHP types.
- Do not silently convert failures into default values.
- Do not assume an API exists in the installed Exact version without verification.

Convert between internal and external representations at appropriate boundaries. Repositories handle storage conversions, Actions coordinate application output contracts, and Controllers construct HTTP responses.

### 7.1 Exact AI Documentation

The Exact library has dedicated AI documentation named `EXACT.MD` at the root of the Exact library repository, not at the root of the application repository.

When working with Exact:

1. Locate the actual root of the Exact repository being used.
2. Read `EXACT.MD` before designing or changing complex Exact API usage.
3. Inspect the library implementation and tests if the documentation does not answer the question.
4. Verify the API against the installed dependency version.
5. Do not copy the entire Exact documentation into the application project.
6. Do not create a duplicate `EXACT.MD` at the application project root.

If the documentation is unavailable, inspect the accessible library source. Do not invent unverified APIs.

## 8. Gauss — Mathematical Computation

Gauss (`pandu/gauss`) is used for available and relevant mathematical operations.

Areas include statistics, probability, linear algebra, probability distributions, time series, optimization, numerical methods, and other mathematical computation provided by the library.

Rules:

- Use Gauss when the required operation is available and appropriate.
- Use native PHP arithmetic for simple operations when it is sufficient.
- Choose floating-point or high-precision computation according to numerical requirements.
- Do not assume every Gauss operation automatically uses high precision.
- Avoid duplicating suitable existing algorithms without a clear reason.
- Separate mathematical algorithms from persistence access.
- Preserve numerical assumptions, units, tolerances, determinism, and boundary behavior.
- Do not assume every Gauss operation accepts or returns Exact types.
- Perform numerical conversions at necessary boundaries and avoid unintended precision loss.

### 8.1 Gauss AI Documentation

The Gauss library has dedicated AI documentation named `GAUSS.MD` at the root of the Gauss library repository, not at the root of the application repository.

When working with Gauss:

1. Locate the actual root of the Gauss repository being used.
2. Read `GAUSS.MD` before designing or changing complex Gauss API usage.
3. Inspect the library implementation and tests if the documentation does not answer the question.
4. Verify type contracts, precision modes, and API availability against the installed dependency version.
5. Do not copy the entire Gauss documentation into the application project.
6. Do not create a duplicate `GAUSS.MD` at the application project root.

If the documentation is unavailable, use inspectable sources. Do not assume unverified APIs or capabilities exist.

## 9. Strategy Module

The Strategy module is where users define trading strategy behavior.

Rules:

- `app/Platform/Strategy/Strategy.php` is the strategy entry point invoked by the Engine according to the framework contract.
- Strategy implementations and indicators belong in the project's designated Strategy area.
- A Strategy receives an ordered sequence of candles, including warm-up data when required by the Engine contract.
- A Strategy returns decisions according to the established contract.
- The Engine is responsible for data retrieval, order simulation, stop-loss/take-profit handling, and backtest results.
- Strategies run in-process, not through an internal HTTP/API layer.
- Pair, timeframe, schedule, and date configuration belong to the Engine or appropriate configuration components.
- Actions orchestrate backtest operations; Process executes the specific tasks they require.
- Use Exact for internal data when appropriate for the Engine contract and available API.
- Do not unilaterally change the public Strategy contract merely to use Exact.
- Do not transfer Engine responsibilities to Strategy, or vice versa, without a clear architectural reason.

## 10. Error Handling and Result Contracts

Failure handling must be explicit and consistent.

Rules:

- Use `Either` or another appropriate result type when the contract must distinguish success from failure.
- Use `Option` for valid absence, not to conceal failures.
- Use `Validated` when multiple validation errors need to be accumulated.
- Use exceptions for exceptional conditions according to the component contract.
- Do not catch exceptions without handling them meaningfully.
- Do not replace failures with empty results, zero, or defaults without a clear domain justification.
- Actions must ensure failures at critical stages do not allow subsequent stages to execute incorrectly.
- Repositories should distinguish missing data, empty results, and persistence failures when that distinction matters to callers.
- Controllers map application results to HTTP statuses and responses according to the external contract.
- Do not expose internal exception details or stack traces in HTTP responses.

## 11. Testing Standards

Use PHPUnit for unit, integration, and regression tests.

### 11.1 Controller Tests

Test request validation, input conversion, Action invocation, response formatting, and mapping of failures to HTTP responses.

Do not place domain algorithm tests in Controller tests.

### 11.2 Action Tests

Test application orchestration, component invocation order when relevant, success and failure handling, workflow termination, and output contracts.

### 11.3 Repository Tests

Test queries, persisted-result mapping, type conversion, persistence behavior, missing-data behavior, and persistence failure handling.

Use integration tests to verify actual database behavior when necessary.

### 11.4 Model Tests

Test casts, relationships, and technical Model behavior when that behavior requires independent verification.

Do not create tests merely to prove that a Model without additional behavior can be instantiated.

### 11.5 Optional Component Tests

- **Data** — test validation, invariants, and value semantics when meaningful behavior exists.
- **Config** — test configuration validation or mapping when needed.
- **Rules** — test decisions, boundary conditions, invariants, and rule combinations.
- **Process** — test algorithms, transformations, branching, determinism, and relevant result handling.

Separate tests for every component class are unnecessary when its behavior is already adequately tested at the appropriate level.

### 11.6 Exact and Gauss Tests

- Test Exact usage against the actual API contract and semantics.
- Test Gauss calculations against trustworthy reference values.
- Use appropriate numerical tolerances for floating-point calculations.
- Verify precision and boundary behavior when high precision is required.
- Do not assume exact equality for floating-point results without justification.

### 11.7 Strategy and Backtest Tests

Test Strategy decisions, warm-up data, candle ordering, determinism, order execution, stop-loss/take-profit behavior, performance calculations, spread, slippage, partial fills, stress tests, and regressions relevant to the change.

### 11.8 General Testing Rules

- Use deterministic test data.
- Separate unit and integration tests as appropriate.
- Add regression tests for reproducible bugs.
- Do not weaken tests merely to make an implementation pass.
- Prefer behavior and public contracts over irrelevant implementation details.
- Run relevant tests and the full suite when feasible.
- Report the tests executed, actual results, failures, and tests not run.

## 12. General PHP Standards

- Use PHP 8.2+ and `declare(strict_types=1);`.
- Declare types explicitly.
- Avoid `mixed` when a more precise type can be expressed.
- Name Actions according to their application operation.
- Name Repositories according to their data-access responsibility.
- Name Data classes according to the information they represent.
- Name Rules according to their domain rule or policy.
- Name Process classes according to the task or capability they execute.
- Avoid duplicating domain rules.
- Do not introduce separate Service or Use Case layers.
- Do not create abstractions merely to follow a design pattern.
- Keep error handling, state changes, and operation ordering easy to trace.
- Use private methods to separate implementation details when that is sufficient.
- Introduce new classes when separation provides a real benefit to responsibility, testing, or maintainability.

### 12.1 Avoid God Functions, God Methods, and God Classes

- Every function and method must have a clear responsibility.
- Actions must not absorb complex algorithms that belong in Process.
- Process must not combine unrelated capabilities.
- Repositories must not become repositories of business logic in the broader sense.
- Controllers must not contain application logic.
- Do not measure quality solely by code length, method count, or class count.
- A long algorithm may remain intact when it is coherent and easier to understand as one unit.
- Split methods when doing so clarifies responsibility, improves testability, or simplifies maintenance.
- Avoid excessively small private methods that add no meaningful abstraction.
- Do not move responsibilities merely to shorten a function.
- Keep the main workflow understandable without excessive delegation layers.

### 12.2 Avoid Overengineering

- Use private methods when separation is needed only to clarify implementation within one class.
- Create Process when a meaningful, independent task or capability exists.
- Create Rules when rules have responsibilities worth isolating.
- Create Data when explicit data representation provides a clear benefit.
- Create Config when genuine configuration needs exist.
- Create Action when an application operation has a clear purpose and contract.
- Create Repository when a real data-access responsibility exists.
- Do not create a class for every line or mechanical step.
- Do not introduce interfaces, base classes, or generic abstractions without concrete needs.
- Avoid circular dependencies between components and features.
- If bidirectional dependencies emerge, reassess the responsibility boundaries.
- Do not force every operation through every layer.

### 12.3 Readability

- An Action's `execute()` method should reveal the operation's main workflow.
- A Process entry point should reveal the main workflow of its task.
- Class, variable, and method names should reflect domain meaning.
- Avoid excessive nesting and duplicated rules.
- Keep type contracts and conversion boundaries consistent.
- Prefer implementations that are easy to understand without compromising correctness.

## 13. Development Procedure

1. Inspect the project structure and established conventions.
2. Identify the feature responsible for the change.
3. Inspect that feature's internal structure before adding or moving classes.
4. Identify requirements, input/output contracts, and domain rules.
5. Determine which application operation belongs in an Action.
6. Decide whether Rules, Process, Data, and Config are necessary based on actual responsibilities.
7. Identify any Repository needed for persistence access.
8. Keep Models aligned with their persistence-representation responsibilities.
9. Consult `EXACT.MD` at the Exact library root before using or changing complex Exact API usage.
10. Consult `GAUSS.MD` at the Gauss library root before using or changing complex Gauss API usage.
11. Verify APIs against the library source and installed dependency versions.
12. Implement changes according to the feature's internal structure and conventions.
13. Add or update tests for changed behavior.
14. Check internal feature consistency, cross-feature dependencies, and potential duplication.
15. Run relevant tests and available quality checks.
16. Check for regressions, public contract changes, and cross-feature impact.
17. Report the changes, tests executed, actual results, and known limitations.

Do not restructure other features merely for visual uniformity. Make cross-feature changes when necessary to preserve contracts, consistent responsibilities, or correct dependency boundaries.

Do not copy Exact or Gauss AI documentation into the application project's root.

## 14. Decision Rules

| Question | Component |
|---|---|
| How are HTTP requests received and responses returned? | Controller |
| What application operation must be executed? | Action |
| How should multiple application steps be coordinated? | Action |
| How is a specific task or capability executed? | Process |
| How are domain rules or criteria applied? | Rules |
| What information must be carried or produced? | Data |
| What parameters need configuration? | Config |
| How is data read or persisted? | Repository |
| How is persisted data represented through Eloquent? | Model |
| How are internal domain types and collections modeled? | Exact |
| How is mathematical computation performed? | Gauss |
| How is behavior verified and regression prevented? | PHPUnit |

Decision rules:

- If code handles HTTP, use Controller.
- If code orchestrates an application operation, use Action.
- If code applies an independent domain rule, consider Rules.
- If code executes an independent task or capability, consider Process.
- If code only carries information, use Data or an appropriate existing type.
- If code supplies parameters that need configuration, use Config.
- If code accesses persistence, use Repository.
- If code represents persisted data, use Model.
- If a process does not require data access, do not add Repository merely for structural consistency.
- If an Action performs one simple task, a separate Process is not mandatory.
- If a rule remains clear within an Action or Process, a separate Rules component is not mandatory.
- If an existing type already expresses the data contract clearly, a separate Data class is not mandatory.
- If configuration has no specific need, a separate Config component is not mandatory.

## 15. Priority Order

Development priorities:

1. Correctness and domain-rule accuracy.
2. Explicit type contracts.
3. Clear separation of responsibilities.
4. Internal feature consistency.
5. Testability and regression prevention.
6. Composability and maintainability.
7. Readable workflows and traceable errors.
8. Implementation simplicity.
9. Relevant, demonstrable efficiency.

Structural consistency matters, but must not be achieved by forcing unnecessary components into the design.

## 16. Core Principles Summary

- **Controller** — handles HTTP transport and delegates operations to Actions.
- **Action** — orchestrates one application operation.
- **Repository** — accesses, maps, and persists data.
- **Model** — represents persisted data using the Anemic Model approach.
- **Data** — represents information through explicit contracts when needed.
- **Config** — provides parameters and settings when needed.
- **Rules** — isolates domain rules or criteria when needed.
- **Process** — executes a specific task or capability when needed.
- **Strategy** — defines trading strategy behavior according to the Engine contract.
- **Exact** — provides internal type and data-structure modeling.
- **Gauss** — provides relevant mathematical computation.
- **PHPUnit** — verifies behavior and prevents regressions.

**The architectural foundation must be uniform; each feature's internal structure must be consistent; complexity may vary between features.**

Every feature should have a predictable, anomaly-free structure. However, simple features do not need to imitate complex ones. Introduce components only when their responsibilities are real, preserve clear boundaries, and avoid unnecessary abstractions.