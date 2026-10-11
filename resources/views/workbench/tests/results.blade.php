@extends('workbench.layout')

@section('title', 'Backtest results')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">RESEARCH / BACKTEST COMPLETE</span><h1>Test results</h1><p>{{ $backtest['pair'] }} <span class="result-separator">/</span> {{ $backtest['timeframe'] }} <span class="result-separator">/</span> {{ $backtest['from'] }} <span class="result-separator">→</span> {{ $backtest['to'] }}</p></div>
        <span class="step-indicator"><b>02</b><span>RESULTS</span><i class="step-done"></i><a href="{{ route('workbench.tests.create') }}">NEW TEST ↗</a></span>
    </div>
    <div class="metric-grid">
        <div class="metric-card"><span class="eyebrow">CANDLES EVALUATED</span><strong>{{ count($backtest['results']) }}</strong><span class="metric-note">strategy decisions returned</span></div>
        <div class="metric-card"><span class="eyebrow">ORDERS OPENED</span><strong>{{ count($backtest['order_history']) }}</strong><span class="metric-note">in-memory order history</span></div>
        <div class="metric-card"><span class="eyebrow">CLOSED ORDERS</span><strong>{{ collect($backtest['order_history'])->whereNotNull('close_date')->count() }}</strong><span class="metric-note">closed by SL, TP, or strategy</span></div>
        <div class="metric-card"><span class="eyebrow">INITIAL CAPITAL</span><strong>{{ number_format($backtest['initial_capital'], 2) }}</strong><span class="metric-note">{{ $backtest['pair'] }} quote currency</span></div>
        <div class="metric-card"><span class="eyebrow">ENDING CAPITAL</span><strong>{{ number_format($backtest['ending_capital'], 2) }}</strong><span class="metric-note">realized trade P/L included</span></div>
        <div class="metric-card"><span class="eyebrow">RISK PER TRADE</span><strong>{{ number_format($backtest['risk_per_trade_percent'], 2) }}%</strong><span class="metric-note">calculated from capital at open</span></div>
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
        <form method="GET" action="{{ route('workbench.tests.results') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
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
        <form method="GET" action="{{ route('workbench.tests.results') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
            @if ($clusterCount !== null)
                <input type="hidden" name="clusters" value="{{ $clusterCount }}">
                <input type="hidden" name="cluster" value="{{ $selectedCluster }}">
            @endif
            <label>Stress mode
                <select name="stress_mode" required>
                    <option value="adverse" @selected(request('stress_mode', 'adverse') === 'adverse')>Adverse excursion</option>
                    <option value="favorable" @selected(request('stress_mode') === 'favorable')>Favorable excursion</option>
                    <option value="combined" @selected(request('stress_mode') === 'combined')>Combined excursion</option>
                </select>
            </label>
            <label>Adverse stress (0–1)
                <input name="adverse_stress" type="number" min="0" max="1" step="0.01" value="{{ request('adverse_stress', '0.50') }}" required>
            </label>
            <label>Favorable stress (0–1)
                <input name="favorable_stress" type="number" min="0" max="1" step="0.01" value="{{ request('favorable_stress', '0.50') }}" required>
            </label>
            <button class="button button-primary" type="submit">Run stress test</button>
        </form>
        <p style="padding:0 20px 18px;color:#718076;font-size:12px">
            Each run uses the stored full backtest, independently of the selected cluster, and does not rerun the strategy. The selected mode determines which excursion(s) are transformed; stress values are fractions from 0 to 1. These are synthetic sensitivity scenarios, not reconstructed market history. Existing SL/TP exits remain unchanged. If both levels are reached in a scenario and their order cannot be known, stop-loss is chosen conservatively; new synthetic exits are priced at the corresponding SL/TP level and use baseline position sizes.
        </p>
        @if ($stressTest !== null)
            <p style="padding:0 20px 16px;color:#526258;font-size:12px">
                {{ strtoupper(request('stress_mode')) }} · adverse {{ number_format($stressTest['adverse_stress'] * 100, 0) }}% · favorable {{ number_format($stressTest['favorable_stress'] * 100, 0) }}%
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
                                : (in_array($metric, ['trade_count', 'stop_loss_count', 'take_profit_count'], true)
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
        <form method="GET" action="{{ route('workbench.tests.results') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 20px">
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
                <thead><tr><th>ORDER</th><th>OPENED</th><th>CLOSED</th><th>ENTRY</th><th>STOP LOSS</th><th>NEAREST TO SL</th><th>TAKE PROFIT</th><th>NEAREST TO TP</th><th>RISK (QUOTE)</th><th>SIZE (BASE UNITS)</th><th>P/L (QUOTE)</th><th>CAPITAL AFTER</th><th>EXIT</th><th>REASON</th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><span class="signal-tag signal-{{ $order['signal'] }}">{{ strtoupper($order['signal']) }}</span><span class="table-meta">#{{ $order['id'] }}</span></td>
                        <td class="mono">{{ $order['open_date'] }}</td>
                        <td class="mono">{{ $order['close_date'] ?? 'OPEN' }}</td>
                        <td class="mono">{{ number_format($order['entry_price'], 6) }}<span class="table-meta">{{ strtoupper($order['entry_price_source']) }}</span></td>
                        <td class="mono">{{ number_format($order['stop_loss'], 6) }}</td>
                        <td class="mono">@if ($order['closest_to_stop_loss']){{ number_format($order['closest_to_stop_loss']['price'], 6) }}<span class="table-meta">{{ strtoupper($order['closest_to_stop_loss']['source']) }} · {{ number_format($order['closest_to_stop_loss']['distance'], 6) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="mono">{{ number_format($order['take_profit'], 6) }}</td>
                        <td class="mono">@if ($order['closest_to_take_profit']){{ number_format($order['closest_to_take_profit']['price'], 6) }}<span class="table-meta">{{ strtoupper($order['closest_to_take_profit']['source']) }} · {{ number_format($order['closest_to_take_profit']['distance'], 6) }}</span>@else <span class="muted">—</span> @endif</td>
                        <td class="mono">{{ number_format($order['risk_amount'], 2) }}<span class="table-meta">{{ number_format($order['capital_at_open'], 2) }} capital</span></td>
                        <td class="mono">{{ number_format($order['position_size'], 2) }}</td>
                        <td class="mono">{{ $order['profit_loss'] !== null ? number_format($order['profit_loss'], 2) : '—' }}</td>
                        <td class="mono">{{ $order['capital_after_close'] !== null ? number_format($order['capital_after_close'], 2) : '—' }}</td>
                        <td class="mono">{{ $order['close_price'] !== null ? number_format($order['close_price'], 6) : '—' }}</td>
                        <td>@if ($order['close_reason'])<span class="reason-tag reason-{{ $order['close_reason'] }}">{{ str_replace('_', ' ', $order['close_reason']) }}</span>@else<span class="muted">Open</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="empty-table">Tidak ada order yang dibuka pada rentang pengujian ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">STRATEGY OUTPUT</span><h2>Candle decisions</h2></div><span class="record-count">{{ $decisions->total() }} DECISIONS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>DATE</th><th>CANDLE CLOSE</th><th>SIGNAL</th><th>STOP LOSS</th><th>TAKE PROFIT</th></tr></thead>
                <tbody>
                @forelse ($decisions as $result)
                    <tr>
                        <td class="mono">{{ $result['candle']['price_date'] }}</td>
                        <td class="mono">{{ $result['candle']['close'] ?? '—' }}</td>
                        <td><span class="signal-tag signal-{{ $result['signal'] }}">{{ strtoupper($result['signal']) }}</span></td>
                        <td class="mono">{{ $result['stop_loss'] !== null ? number_format($result['stop_loss'], 6) : '—' }}</td>
                        <td class="mono">{{ $result['take_profit'] !== null ? number_format($result['take_profit'], 6) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-table">Tidak ada candle dalam jadwal aktif yang dipilih.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($decisions->hasPages())
            <div class="pagination-wrap">{{ $decisions->links() }}</div>
        @endif
    </section>
@endsection
