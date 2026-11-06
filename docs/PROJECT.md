# Nikaido — Project Overview

## 1. About the Project

Nikaido is a PHP and Laravel-based research workbench for intraday and swing
trading research on CFD and spot market data. It helps traders organize and
explore historical prices and related context, run candle-based backtests, and
review results under explicit instrument, cost, and execution assumptions.

Nikaido supports research and evaluation, not live order execution, broker
connectivity, or trading signals. The current backtest requires a CFD contract
profile for every pair. Spot price histories can be used for price-based
research, but the engine does not model spot ownership, cash settlement, or
asset custody; see [Market support](features/market-support.md) for details.
It does not provide trading strategies or guarantee that historical results
will predict future performance.

Nikaido uses Laravel as its application foundation, Exact for explicit domain type and data modeling, and Gauss for mathematical operations suited to the project's computational requirements.

Development prioritizes correctness, maintainability, composability, and architectural simplicity. Abstractions and dependencies are introduced only when they provide tangible benefits for the project's actual needs.

## 2. Objectives

- Provide a focused workflow for researching intraday and swing strategy ideas using user-supplied CFD or spot price histories.
- Make market data, timeframe selection, research context, instrument assumptions, and test configuration inspectable and repeatable.
- Evaluate historical strategy behavior while accounting for configurable transaction costs and execution assumptions.
- Provide robustness testing to measure how sensitive strategy results are to changes in parameters, assumptions, and simulation conditions.
- Separate strategy logic, backtesting processes, data access, and application interfaces through clearly defined responsibilities.
- Use explicit domain types to reduce ambiguous states and data representation errors.
- Use mathematical operations suited to domain requirements instead of reimplementing functionality that is already available.
- Ensure that features and bug fixes have relevant tests.
- Avoid overengineering, premature abstractions, and unnecessary dependencies.
- Help users assess the limitations and resilience of backtest results without treating historical performance as a guarantee of future profitability.