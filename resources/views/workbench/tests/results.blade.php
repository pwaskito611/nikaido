@extends('workbench.layout')

@section('title', 'Backtest results')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">{{ $resultsEyebrow }}</span><h1>{{ $resultsTitle }}</h1><p>{{ $backtest['pair'] }} <span class="result-separator">/</span> {{ $backtest['timeframe'] }} <span class="result-separator">/</span> {{ $backtest['from'] }} <span class="result-separator">→</span> {{ $backtest['to'] }}</p></div>
        <span class="step-indicator"><b>02</b><span>RESULTS</span><i class="step-done"></i><a href="{{ $returnUrl }}">BACK ↗</a></span>
    </div>
    @if ($successfulPairCount !== null)
        @if (($backtest['capital_mode'] ?? 'separate') === 'shared')
            <p class="analysis-note">Shared portfolio capital · {{ $successfulPairCount }} successful pair(s). Pair workers generate their results independently; a deterministic portfolio manager then synchronizes entries and exits chronologically. Exits release reserved stop-loss risk and realize P/L before same-time entries are allocated. If simultaneous entries exceed free risk capital, their quantities are reduced proportionally; strategy signals retain their requested quantities, while the order history shows the actual allocated quantity. P/L is combined as reported, without FX conversion, so all pair results must use the same account unit.</p>
        @else
            <p class="analysis-note">Separate capital per pair · {{ $successfulPairCount }} successful pair(s). Each pair is backtested independently with its own starting capital and does not share or reserve capital with other pairs. Overall initial and ending capital are sums across successful pairs; the average starting and ending capital per pair are shown below. Ending capital includes realized P/L only; floating P/L is not included. P/L is combined as reported, without FX conversion, so all pair results must use the same account unit.</p>
        @endif
    @endif
    @php
        $executionConfig = $backtest['execution_config'] ?? null;
        $tradeCostConfig = $executionConfig['trade_costs'] ?? ['enabled' => false, 'components' => []];
        $tradeCostTotals = collect($backtest['order_history'])->reduce(function (array $totals, array $order): array {
            foreach (($order['trade_costs'] ?? []) as $component => $amount) {
                $totals[$component] = ($totals[$component] ?? 0) + (float) $amount;
            }
            return $totals;
        }, []);
        $totalAdditionalTradeCosts = array_sum($tradeCostTotals);
        $modeledCostLabels = collect($tradeCostConfig['components'] ?? [])
            ->filter(fn (array $component) => $tradeCostConfig['enabled'] && ($component['enabled'] ?? false))
            ->keys()
            ->map(fn (string $component) => str_replace('_', ' ', $component))
            ->join(', ');
    @endphp
    @if ($executionConfig !== null)
        <p class="analysis-note">
            Execution assumptions:
            slippage {{ $executionConfig['slippage_enabled'] ? number_format($executionConfig['slippage_pips'], 2).' pip(s)' : 'off' }}
            @if ($executionConfig['slippage_enabled'])
                (pip size {{ $executionConfig['slippage_pip_size'] === null ? 'FX convention: 0.0001; JPY quote: 0.01' : number_format($executionConfig['slippage_pip_size'], 8) }})
            @endif
            · partial fills {{ $executionConfig['partial_fill_enabled'] ? number_format($executionConfig['partial_fill_ratio'] * 100, 2).'% per attempt; '.$executionConfig['unfilled_order_policy'].' remainder' : 'off' }}.
            Synthetic fills do not use candle volume or order-book liquidity.
        </p>
    @endif
    @if ($tradeCostConfig !== null)
        <p class="analysis-note">
            Trade costs:
            @if (! ($tradeCostConfig['enabled'] ?? false))
                disabled.
            @elseif ($modeledCostLabels === '')
                enabled, but no cost component was configured; no additional cost was modeled.
            @else
                modeled components: {{ $modeledCostLabels }}. Other fees are not modeled.
            @endif
            No costs are inferred from the instrument or market type.
        </p>
    @endif
    <div class="metric-grid">
        <div class="metric-card"><span class="eyebrow">CANDLES EVALUATED</span><strong>{{ count($backtest['results']) }}</strong><span class="metric-note">strategy decisions returned</span></div>
        <div class="metric-card"><span class="eyebrow">ORDERS OPENED</span><strong>{{ collect($backtest['order_history'])->reject(fn ($order) => ! empty($order['portfolio_entry_rejected']))->count() }}</strong><span class="metric-note">excludes entries rejected for insufficient portfolio risk capital</span></div>
        <div class="metric-card"><span class="eyebrow">CLOSED ORDERS</span><strong>{{ collect($backtest['order_history'])->whereNotNull('close_date')->count() }}</strong><span class="metric-note">closed by SL, TP, or strategy</span></div>
        <div class="metric-card"><span class="eyebrow">PROFIT FACTOR</span><strong>{{ $closedTradeCount === 0 ? '—' : ($profitFactor === null ? '—' : (is_infinite($profitFactor) ? '∞' : number_format($profitFactor, 2))) }}</strong><span class="metric-note">profitable net P/L ÷ losing net P/L across closed trades</span></div>
        <div class="metric-card"><span class="eyebrow">AVERAGE DRAWDOWN</span><strong>{{ number_format($averageDrawdownPercent, 2) }}%</strong><span class="metric-note">mean realized-equity drawdown below all-time high</span></div>
        <div class="metric-card"><span class="eyebrow">MEDIAN DRAWDOWN</span><strong>{{ number_format($medianDrawdownPercent, 2) }}%</strong><span class="metric-note">median realized-equity drawdown below all-time high</span></div>
        <div class="metric-card"><span class="eyebrow">MAXIMUM DRAWDOWN</span><strong>{{ number_format($maximumDrawdownPercent, 2) }}%</strong><span class="metric-note">largest realized-equity decline from all-time high</span></div>
        <div class="metric-card"><span class="eyebrow">INITIAL CAPITAL</span><strong>{{ number_format($backtest['initial_capital'], 2) }}</strong><span class="metric-note">{{ $successfulPairCount === null ? $backtest['pair'].' quote currency' : (($backtest['capital_mode'] ?? 'separate') === 'shared' ? 'one shared portfolio balance' : 'sum across independent pair accounts') }}</span></div>
        <div class="metric-card"><span class="eyebrow">ENDING CAPITAL</span><strong>{{ number_format($backtest['ending_capital'], 2) }}</strong><span class="metric-note">realized trade P/L included</span></div>
        <div class="metric-card"><span class="eyebrow">ADDITIONAL TRADE COSTS</span><strong>{{ number_format($totalAdditionalTradeCosts, 2) }}</strong><span class="metric-note">{{ ($tradeCostConfig['enabled'] ?? false) ? 'configured modeled costs only' : 'disabled' }}</span></div>
        @foreach ([
            'commission' => 'COMMISSION',
            'exchange_fee' => 'EXCHANGE FEES',
            'clearing_fee' => 'CLEARING FEES',
            'regulatory_fee' => 'REGULATORY FEES',
            'transaction_tax' => 'TRANSACTION TAXES',
            'borrow_fee' => 'BORROW FEES',
            'financing_fee' => 'FINANCING / HOLDING COSTS',
        ] as $component => $componentLabel)
            @if (($tradeCostConfig['enabled'] ?? false) && ($tradeCostConfig['components'][$component]['enabled'] ?? false))
                <div class="metric-card"><span class="eyebrow">{{ $componentLabel }}</span><strong>{{ number_format($tradeCostTotals[$component] ?? 0, 2) }}</strong><span class="metric-note">deducted from net P/L</span></div>
            @endif
        @endforeach
        @if ($successfulPairCount !== null && ($backtest['capital_mode'] ?? 'separate') === 'separate')
            <div class="metric-card"><span class="eyebrow">AVERAGE INITIAL CAPITAL / PAIR</span><strong>{{ number_format($backtest['average_initial_capital_per_pair'], 2) }}</strong><span class="metric-note">mean starting capital across successful pairs</span></div>
            <div class="metric-card"><span class="eyebrow">AVERAGE ENDING CAPITAL / PAIR</span><strong>{{ number_format($backtest['average_ending_capital_per_pair'], 2) }}</strong><span class="metric-note">mean realized ending capital across successful pairs</span></div>
        @endif
    </div>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">CAPITAL CURVE</span><h2>Capital movement</h2></div><span class="record-count">{{ $clusterCount === null ? 'FULL HISTORY' : 'CLUSTER '.$selectedCluster.' / '.$clusterCount }}</span></div>
        <div style="padding: 16px 20px 20px">
            <svg viewBox="0 0 640 180" role="img" aria-label="Capital movement from {{ number_format($capitalChart['start'], 2) }} to {{ number_format($capitalChart['end'], 2) }}" style="display:block;width:100%;height:220px">
                <line x1="24" y1="12" x2="616" y2="12" stroke="#e4e8e0"/>
                <line x1="24" y1="87" x2="616" y2="87" stroke="#e4e8e0"/>
                <line x1="24" y1="162" x2="616" y2="162" stroke="#e4e8e0"/>
                <polyline points="{{ $capitalChart['points'] }}" fill="none" stroke="#477a3c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="chart-axis"><span>{{ number_format($capitalChart['minimum'], 2) }} MIN</span><span>{{ number_format($capitalChart['maximum'], 2) }} MAX</span></div>
        </div>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">CLUSTER ANALYSIS</span><h2>{{ $clusterCount === null ? 'Full history' : 'Cluster '.$selectedCluster.' analysis' }}</h2></div><span class="record-count">{{ $clusterAnalysis['orders'] }} ORDERS</span></div>
        <div class="metric-grid" style="padding: 0 20px 20px; margin: 0">
            <div class="metric-card"><span class="eyebrow">CLOSED / OPEN</span><strong>{{ $clusterAnalysis['closed'] }} / {{ $clusterAnalysis['orders'] - $clusterAnalysis['closed'] }}</strong></div>
            <div class="metric-card"><span class="eyebrow">WINS / LOSSES</span><strong>{{ $clusterAnalysis['wins'] }} / {{ $clusterAnalysis['losses'] }}</strong></div>
            <div class="metric-card"><span class="eyebrow">WIN RATE</span><strong>{{ number_format($clusterAnalysis['win_rate'], 1) }}%</strong></div>
            <div class="metric-card"><span class="eyebrow">NET P/L</span><strong>{{ number_format($clusterAnalysis['net_profit_loss'], 2) }}</strong></div>
            <div class="metric-card"><span class="eyebrow">CLUSTER START CAPITAL</span><strong>{{ number_format($clusterAnalysis['capital_start'], 2) }}</strong></div>
            <div class="metric-card"><span class="eyebrow">CLUSTER END CAPITAL</span><strong>{{ number_format($clusterAnalysis['capital_end'], 2) }}</strong></div>
        </div>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">DATA SCOPE</span><h2>Full history or cluster</h2></div></div>
        <form method="GET" action="{{ $analysisAction }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
            <label>Clusters
                <select name="clusters" onchange="this.form.submit()">
                    <option value="full" @selected($clusterCount === null)>Full history</option>
                    @foreach (range(2, 10) as $count)
                        <option value="{{ $count }}" @selected($clusterCount === $count)>{{ $count }} clusters</option>
                    @endforeach
                </select>
            </label>
            @if ($clusterCount !== null)
                <label>View cluster
                    <select name="cluster" onchange="this.form.submit()">
                        @foreach ($clusterOptions as $clusterOption)
                            <option value="{{ $clusterOption }}" @selected($selectedCluster === $clusterOption)>Cluster {{ $clusterOption }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="button button-secondary" type="submit">Apply view</button>
        </form>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">SYNTHETIC SENSITIVITY ANALYSIS</span><h2>Fluctuation Stress Test</h2></div><span class="record-count">FULL BASELINE</span></div>
        <form method="GET" action="{{ $analysisAction }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
            @if ($clusterCount !== null)
                <input type="hidden" name="clusters" value="{{ $clusterCount }}">
                <input type="hidden" name="cluster" value="{{ $selectedCluster }}">
            @endif
            <label>Additional stress factor
                <input name="stress_x" type="number" min="0" step="0.01" value="{{ request('stress_x', '0.50') }}" required>
            </label>
            <button class="button button-primary" type="submit">Run stress test</button>
        </form>
        <p style="padding:0 20px 10px;color:#526258;font-size:12px">
            Formula: <strong>additional adverse move = x × excursion</strong>. The excursion is the largest drawdown before a winning trade or largest run-up before a losing trade. This extra move is applied to the baseline exit, so x = 0 leaves the baseline result unchanged. For long positions the exit moves down; for short positions it moves up, capped at the stop-loss.
        </p>
        <p style="padding:0 20px 18px;color:#718076;font-size:12px">
            Each run uses the stored full backtest, independently of the selected cluster, and does not rerun the strategy. For profitable trades, the measured maximum drawdown before profit is used; for losing trades, the maximum run-up before loss is used. The additional adverse exit move is x × that excursion, applied to the baseline exit; x = 0 therefore reproduces the baseline P/L. A synthetic exit that reaches the stop-loss is capped at the stop-loss execution price. The P/L adjustment uses the position actually closed and its P/L multiplier, so the original slippage and partial fills already reflected in baseline execution are not applied a second time.
        </p>
        @if ($stressTest !== null)
            <p style="padding:0 20px 16px;color:#526258;font-size:12px">
                Stress parameter x = {{ number_format($stressTest['x'], 2) }} · additional adverse move {{ number_format($stressTest['x'] * 100, 0) }}% of excursion
            </p>
            @php
                $stressRows = [
                    'net_profit' => 'Net profit',
                    'expectancy' => 'Expectancy per trade',
                    'profit_factor' => 'Profit factor',
                    'max_drawdown' => 'Maximum drawdown',
                    'trade_count' => 'Closed trades',
                    'stop_loss_count' => 'Stop-loss count',
                    'stop_loss_frequency' => 'Stop-loss frequency (%)',
                    'take_profit_count' => 'Take-profit count',
                    'take_profit_frequency' => 'Take-profit frequency (%)',
                    'stress_test_count' => 'Stress-test exit count',
                    'stress_test_frequency' => 'Stress-test exit frequency (%)',
                    'profitable_stress_exit_count' => 'Profitable stress exits',
                    'profitable_stress_exit_frequency' => 'Profitable stress exits (% of stress exits)',
                    'losing_stress_exit_count' => 'Losing stress exits',
                    'losing_stress_exit_frequency' => 'Losing stress exits (% of stress exits)',
                    'breakeven_stress_exit_count' => 'Breakeven stress exits',
                    'breakeven_stress_exit_frequency' => 'Breakeven stress exits (% of stress exits)',
                    'profitable_trade_count' => 'Profitable trades',
                    'profitable_trade_frequency' => 'Win rate (% of closed trades)',
                    'losing_trade_count' => 'Losing trades',
                    'losing_trade_frequency' => 'Loss rate (% of closed trades)',
                    'breakeven_trade_count' => 'Breakeven trades',
                    'breakeven_trade_frequency' => 'Breakeven rate (% of closed trades)',
                    'other_exit_count' => 'Other exit count',
                    'other_exit_frequency' => 'Other exit frequency (%)',
                ];
            @endphp
            <div class="table-scroll">
                <table>
                    <thead><tr><th>METRIC</th><th>BASELINE</th><th>STRESS SCENARIO</th><th>CHANGE</th></tr></thead>
                    <tbody>
                    @foreach ($stressRows as $metric => $label)
                        @php
                            $baselineValue = $stressTest['baseline'][$metric];
                            $scenarioValue = $stressTest['scenario'][$metric];
                            $changeValue = $baselineValue !== null && $scenarioValue !== null
                                ? $scenarioValue - $baselineValue
                                : null;
                            $formatMetric = static fn ($value): string => $metric === 'profit_factor' && $value === null
                                ? '∞'
                                : (in_array($metric, ['trade_count', 'stop_loss_count', 'take_profit_count', 'stress_test_count', 'other_exit_count', 'profitable_stress_exit_count', 'losing_stress_exit_count', 'breakeven_stress_exit_count', 'profitable_trade_count', 'losing_trade_count', 'breakeven_trade_count'], true)
                                    ? number_format((float) $value, 0)
                                    : number_format((float) $value, 2));
                        @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="mono">{{ $formatMetric($baselineValue) }}</td>
                            <td class="mono">{{ $formatMetric($scenarioValue) }}</td>
                            <td class="mono">{{ $changeValue === null ? '—' : ($changeValue > 0 ? '+' : '').$formatMetric($changeValue) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">TRADE-SEQUENCE ANALYSIS</span><h2>Monte Carlo Simulation</h2></div><span class="record-count">HISTORICAL P/L INPUT</span></div>
        <form method="GET" action="{{ $analysisAction }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
            <input type="hidden" name="monte_carlo" value="1">
            @if ($clusterCount !== null)
                <input type="hidden" name="clusters" value="{{ $clusterCount }}">
                <input type="hidden" name="cluster" value="{{ $selectedCluster }}">
            @endif
            <label>Method
                <select name="method" required>
                    <option value="shuffle" @selected(request('method', 'shuffle') === 'shuffle')>Shuffle (without replacement)</option>
                    <option value="bootstrap" @selected(request('method') === 'bootstrap')>Bootstrap (with replacement)</option>
                </select>
            </label>
            <label>Simulations
                <input name="simulation_count" type="number" min="1" max="10000" value="{{ request('simulation_count', 1000) }}" required>
            </label>
            <label>Trades per simulation
                <input name="trades_per_simulation" type="number" min="1" max="10000" value="{{ request('trades_per_simulation') }}" placeholder="All historical trades">
            </label>
            <label>Initial capital
                <input name="mc_initial_capital" type="number" min="0.01" step="0.01" value="{{ request('mc_initial_capital', $backtest['initial_capital']) }}" required>
            </label>
            <label>Risk model
                <select name="risk_model" required>
                    <option value="fixed_monetary_pl" @selected(request('risk_model', 'fixed_monetary_pl') === 'fixed_monetary_pl')>Fixed monetary P/L</option>
                    <option value="compounded_return" @selected(request('risk_model') === 'compounded_return')>Compounded trade return</option>
                </select>
            </label>
            <label>Random seed
                <input name="random_seed" type="number" min="0" max="2147483647" value="{{ request('random_seed') }}" placeholder="Optional">
            </label>
            <label>Drawdown threshold (%)
                <input name="drawdown_threshold" type="number" min="0" max="100" step="0.1" value="{{ request('drawdown_threshold', 20) }}" required>
            </label>
            <button class="button button-primary" type="submit">Run Monte Carlo</button>
        </form>
        <div style="padding:0 20px 18px;color:#718076;font-size:12px">
            <p>Uses only closed historical trades and their stored P/L; the strategy is not rerun and source data is unchanged. Shuffle uses every historical trade once per simulation; bootstrap samples with replacement and defaults to the historical trade count. A supplied trade count different from the historical count is valid only for bootstrap.</p>
            <p>Fixed monetary P/L adds each stored P/L to equity. Compounded return uses each trade's stored P/L divided by its capital at open as the trade return; this model resamples those per-trade returns rather than nominal P/L amounts. Equity reaching zero or below is floored at zero and that simulation stops. Maximum drawdown is reported both as a percentage of peak equity and as a monetary amount; the configured threshold and its probability use percent.</p>
            <p>Bootstrap treats trades as independent observations and does not preserve temporal or market-regime dependencies. Neither method simulates changes in spread, slippage, liquidity, volatility, or market regime; results do not guarantee future performance or remove overfitting risk. The 95th percentile is a distribution statistic, not a guaranteed loss limit.</p>
            @error('monte_carlo')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('simulation_count')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('trades_per_simulation')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('method')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('mc_initial_capital')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('random_seed')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('risk_model')<p style="color:#a33">{{ $message }}</p>@enderror
            @error('drawdown_threshold')<p style="color:#a33">{{ $message }}</p>@enderror
        </div>
        @if ($monteCarlo !== null)
            <p style="padding:0 20px 16px;color:#526258;font-size:12px">
                {{ strtoupper($monteCarlo['config']['method']) }} · {{ number_format($monteCarlo['config']['simulation_count']) }} simulations · {{ number_format($monteCarlo['config']['trades_per_simulation']) }} trades/simulation · {{ str_replace('_', ' ', $monteCarlo['config']['risk_model']) }}@if ($monteCarlo['config']['random_seed'] !== null) · seed {{ $monteCarlo['config']['random_seed'] }}@endif
            </p>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>FINAL EQUITY</th><th>MEDIAN MAX DD (%)</th><th>95TH PCTL MAX DD (%)</th><th>WORST MAX DD (%)</th><th>MEDIAN MAX DD (AMOUNT)</th><th>95TH PCTL MAX DD (AMOUNT)</th><th>WORST MAX DD (AMOUNT)</th><th>PROBABILITY OF LOSS</th><th>PROBABILITY DD ≥ {{ number_format($monteCarlo['config']['drawdown_threshold'], 1) }}%</th></tr></thead>
                    <tbody><tr>
                        <td class="mono">Median {{ number_format($monteCarlo['metrics']['final_equity']['median'], 2) }}<span class="table-meta">Mean {{ number_format($monteCarlo['metrics']['final_equity']['mean'], 2) }} · P5 {{ number_format($monteCarlo['metrics']['final_equity']['p5'], 2) }} · P25 {{ number_format($monteCarlo['metrics']['final_equity']['p25'], 2) }} · P75 {{ number_format($monteCarlo['metrics']['final_equity']['p75'], 2) }} · P95 {{ number_format($monteCarlo['metrics']['final_equity']['p95'], 2) }}</span></td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_percent']['median'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_percent']['p95'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_percent']['worst'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_amount']['median'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_amount']['p95'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['maximum_drawdown_amount']['worst'], 2) }}</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['probability_of_loss'], 2) }}%</td>
                        <td class="mono">{{ number_format($monteCarlo['metrics']['probability_of_drawdown'], 2) }}%</td>
                    </tr></tbody>
                </table>
            </div>
            <div class="table-scroll" style="margin-top:12px">
                <table>
                    <thead><tr><th colspan="3">FINAL EQUITY DISTRIBUTION ({{ $monteCarlo['config']['initial_capital'] }})</th><th colspan="3">MAX DRAWDOWN DISTRIBUTION (%)</th></tr><tr><th>FROM</th><th>TO</th><th>SIMULATIONS</th><th>FROM (%)</th><th>TO (%)</th><th>SIMULATIONS</th></tr></thead>
                    <tbody>
                    @foreach ($monteCarlo['histograms']['final_equity'] as $index => $equityBin)
                        @php($drawdownBin = $monteCarlo['histograms']['maximum_drawdown_percent'][$index])
                        <tr>
                            <td class="mono">{{ number_format($equityBin['from'], 2) }}</td>
                            <td class="mono">{{ number_format($equityBin['to'], 2) }}</td>
                            <td class="mono">{{ $equityBin['count'] }}</td>
                            <td class="mono">{{ number_format($drawdownBin['from'], 2) }}</td>
                            <td class="mono">{{ number_format($drawdownBin['to'], 2) }}</td>
                            <td class="mono">{{ $drawdownBin['count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">ORDER LIFECYCLE</span><h2>Order history</h2></div><span class="record-count">{{ count($orders) }} ORDERS</span></div>
        <div class="table-scroll">
            <table class="wide-table">
                <thead><tr>@if (isset($orders[0]['pair']))<th>PAIR</th>@endif<th>ORDER</th><th>OPENED</th><th>CLOSED</th><th>ENTRY</th><th>STOP LOSS</th><th>NEAREST TO SL</th><th>TAKE PROFIT</th><th>NEAREST TO TP</th><th>RISK (QUOTE)</th><th>SIZE (BASE UNITS)</th><th>P/L (QUOTE)</th><th>CAPITAL AFTER</th><th>EXIT</th><th>REASON</th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        @if (isset($order['pair']))<td><span class="instrument-code">{{ $order['pair'] }}</span></td>@endif
                        <td><span class="signal-tag signal-{{ $order['signal'] }}">{{ strtoupper($order['signal']) }}</span><span class="table-meta">#{{ $order['id'] }}</span></td>
                        <td class="mono">{{ $order['open_date'] }}</td>
                        <td class="mono">{{ ! empty($order['portfolio_entry_rejected']) ? 'REJECTED' : ($order['close_date'] ?? 'OPEN') }}</td>
                        <td class="mono">{{ number_format($order['entry_price'], 6) }}<span class="table-meta">{{ strtoupper($order['entry_price_source']) }}</span></td>
                        <td class="mono">{{ number_format($order['stop_loss'], 6) }}</td>
                        <td class="mono">@if ($order['closest_to_stop_loss']){{ number_format($order['closest_to_stop_loss']['price'], 6) }}<span class="table-meta">{{ strtoupper($order['closest_to_stop_loss']['source']) }} · {{ number_format($order['closest_to_stop_loss']['distance'], 6) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="mono">{{ number_format($order['take_profit'], 6) }}</td>
                        <td class="mono">@if ($order['closest_to_take_profit']){{ number_format($order['closest_to_take_profit']['price'], 6) }}<span class="table-meta">{{ strtoupper($order['closest_to_take_profit']['source']) }} · {{ number_format($order['closest_to_take_profit']['distance'], 6) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="mono">{{ number_format($order['risk_amount'], 2) }}<span class="table-meta">{{ number_format($order['capital_at_open'], 2) }} capital · {{ number_format($order['requested_risk_amount'] ?? $order['risk_amount'], 2) }} loss at stop</span></td>
                        <td class="mono">{{ number_format($order['position_size'], 8) }} / {{ number_format($order['requested_position_size'] ?? $order['position_size'], 8) }}<span class="table-meta">filled / requested · strategy quantity {{ number_format($order['strategy_quantity'] ?? $order['requested_position_size'] ?? $order['position_size'], 8) }} {{ ($order['quantity_unit'] ?? 'units') === 'contract' ? 'contract(s)' : ((($order['quantity_unit'] ?? 'units') === 'lot') ? 'lot(s)' : 'unit(s)') }} @if (($order['quantity_unit'] ?? 'units') === 'contract') × {{ number_format($order['quantity_multiplier'] ?? 1, 8) }} quote-currency units per price point per contract @elseif (($order['quantity_unit'] ?? 'units') === 'lot') × {{ number_format($order['quantity_multiplier'] ?? 1, 8) }} units per lot @endif · {{ number_format($order['remaining_position_size'] ?? 0, 8) }} open · {{ number_format($order['remaining_entry_quantity'] ?? 0, 8) }} entry pending</span></td>
                        <td class="mono">
                            @if ($order['profit_loss'] !== null)
                                {{ number_format($order['profit_loss'], 2) }}
                            @elseif (! empty($order['exit_fills']))
                                {{ number_format($order['realized_profit_loss'], 2) }}<span class="table-meta">realized; position remains open</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="mono">
                            @if ($order['capital_after_close'] !== null)
                                {{ number_format($order['capital_after_close'], 2) }}
                            @elseif (! empty($order['exit_fills']))
                                {{ number_format($order['exit_fills'][array_key_last($order['exit_fills'])]['capital_after'], 2) }}<span class="table-meta">after partial exit</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="mono">{{ $order['close_price'] !== null ? number_format($order['close_price'], 6) : '—' }}</td>
                        <td>@if ($order['close_reason'])<span class="reason-tag reason-{{ $order['close_reason'] }}">{{ str_replace('_', ' ', $order['close_reason']) }}</span>@else<span class="muted">Open</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ isset($orders[0]['pair']) ? 15 : 14 }}" class="empty-table">No orders were opened during this test range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">STRATEGY OUTPUT</span><h2>Candle decisions</h2></div><span class="record-count">{{ $decisions->total() }} DECISIONS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr>@if (isset($decisions->items()[0]['pair']))<th>PAIR</th>@endif<th>DATE</th><th>CANDLE CLOSE</th><th>SIGNAL</th><th>QUANTITY</th><th>STOP LOSS</th><th>TAKE PROFIT</th></tr></thead>
                <tbody>
                @forelse ($decisions as $result)
                    <tr>
                        @if (isset($result['pair']))<td><span class="instrument-code">{{ $result['pair'] }}</span></td>@endif
                        <td class="mono">{{ $result['candle']['price_date'] }}</td>
                        <td class="mono">{{ $result['candle']['close'] ?? '—' }}</td>
                        <td><span class="signal-tag signal-{{ $result['signal'] }}">{{ strtoupper($result['signal']) }}</span></td>
                        <td class="mono">{{ ($result['quantity'] ?? null) === null ? '—' : number_format((float) $result['quantity'], 8).' '.(($result['quantity_unit'] ?? 'units') === 'contract' ? 'contract(s)' : ((($result['quantity_unit'] ?? 'units') === 'lot') ? 'lot(s)' : 'unit(s)')) }}</td>
                        <td class="mono">{{ $result['stop_loss'] !== null ? number_format($result['stop_loss'], 6) : '—' }}</td>
                        <td class="mono">{{ $result['take_profit'] !== null ? number_format($result['take_profit'], 6) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ isset($decisions->items()[0]['pair']) ? 7 : 6 }}" class="empty-table">No candles are available within the selected active schedule.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($decisions->hasPages())
            <div class="pagination-wrap">{{ $decisions->links() }}</div>
        @endif
    </section>
@endsection
