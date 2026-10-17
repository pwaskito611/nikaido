# Nikaido — Project Overview

## 1. About the Project

Nikaido is a PHP and Laravel-based backtesting framework for systematic trading research. It focuses on strategy performance evaluation, transaction cost and execution simulation, and robustness testing to understand how sensitive strategy results are to changes in assumptions and market conditions.

Nikaido is designed to let users focus on developing and testing trading strategies while the backtesting engine handles simulation and result recording. It serves as a research and evaluation tool, not as a provider of trading strategies or trading bots.

Nikaido uses Laravel as its application foundation, Exact for explicit domain type and data modeling, and Gauss for mathematical operations suited to the project's computational requirements.

Development prioritizes correctness, maintainability, composability, and architectural simplicity. Abstractions and dependencies are introduced only when they provide tangible benefits for the project's actual needs.

## 2. Objectives

- Provide a framework for developing, running, and evaluating systematic trading strategies.
- Evaluate strategy performance while accounting for transaction costs, execution assumptions, and relevant testing configurations.
- Provide robustness testing to measure how sensitive strategy results are to changes in parameters, assumptions, and simulation conditions.
- Separate strategy logic, backtesting processes, data access, and application interfaces through clearly defined responsibilities.
- Use explicit domain types to reduce ambiguous states and data representation errors.
- Use mathematical operations suited to domain requirements instead of reimplementing functionality that is already available.
- Ensure that features and bug fixes have relevant tests.
- Avoid overengineering, premature abstractions, and unnecessary dependencies.
- Help users assess the limitations and resilience of backtest results without treating historical performance as a guarantee of future profitability.