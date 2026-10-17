# Shared-capital simulation

## Scope and availability

Shared capital is available for a market-type backtest. Each selected pair
first produces its own decision and order history. After pair jobs finish,
the portfolio synchronizer replays those records in timestamp order and
scales entries against one configured starting-capital pool. This is
post-simulation allocation of order histories, not a shared strategy object
that sees every instrument live.

Capital, requested risk, fees, and P&L are treated as directly comparable
numbers. There is no FX conversion, broker margin, leverage, or notional
reservation. Use shared mode only for instruments with compatible
denominations and meaningfully compatible risk units.

## Allocation and event ordering

The synchronizer creates events for order opens, entry fills, and exit fills.
For equal timestamps it processes exits first, then unfilled order-entry
requests, then entry-fill cost events. Exits update capital by net realized
fill P&L and release reserved stop-risk in proportion to the quantity closed;
a final exit fill releases any residual reservation. Entries at the same
timestamp are allocated in deterministic pair-name/order-ID order.

For each entry, requested risk is adapted by the ratio of current shared
capital to the local capital at which that pair's job sized the order. Existing
open risk is reserved. Available capital for allocation is
`max(0, sharedCapital - reservedRisk)`. Fixed per-order entry charges are
removed first; variable entry costs are included in allocation demand. If
total risk demand plus variable entry costs exceeds the remaining allocation
budget, a common factor scales the competing requests proportionally.
The resulting quantity, risk, costs, and capital-before fields are recorded
on the managed order. A zero allocation can reject an entry.

Risk reservation is not a debit of position notional. Actual cash-like
capital changes through entry costs and realized exit P&L. Partial exits
release only the risk associated with their closed quantity. `keep` versus
`cancel` affects whether unfilled requested entry quantity participates in
risk sizing; it does not add a market-margin check.

## Worked allocation example

Assume illustrative shared capital of 1,000, no active reservations, no fees,
and two entries at the same timestamp requesting stop-risk of 700 and 500.
Total demand is 1,200, so the common allocation factor is
`1,000 / 1,200 = 0.833333…`. The allocated risks are approximately 583.33 and
416.67. Each requested position quantity is scaled by the corresponding
factor; neither entry receives priority solely because its job finished
first. This example omits fees and all other existing positions.

## Position sizing and accounting limitations

The strategy in each pair job receives that job's evolving local capital.
Portfolio synchronization later rescales recorded order quantities; it does
not rerun strategy logic with the global capital at every event. Results
therefore reflect the implemented allocation transform, not a fully
interleaved portfolio strategy.

Separate-capital mode is different: each pair starts with the configured
capital, and aggregate reporting replays the pair's recorded costs and exits
on a common timestamp timeline from the sum of successful pair initial
capital. Neither mode marks open positions to market. Open risk can be
reserved in shared mode while unrealized P&L remains absent from capital.
There is no guarantee capital cannot become negative due to costs or losses.

## Verification and related documentation

The synchronizer and ordering/allocation rules are covered by
[`PortfolioCapitalSynchronizationDomainTest`](../../tests/Unit/Workbench/Domain/MarketTypeBacktests/PortfolioCapitalSynchronizationDomainTest.php).
End-to-end multi-pair cases are in
[`MarketTypeBacktestTest`](../../tests/Feature/MarketTypeBacktestTest.php).

Related: [multi-pair backtesting](multi-pair-backtesting.md),
[capital and position sizing](../user-guide/capital-and-position-sizing.md),
[transaction costs](transaction-costs.md), and
[concurrency](concurrency.md).
