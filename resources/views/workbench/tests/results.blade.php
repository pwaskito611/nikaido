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
