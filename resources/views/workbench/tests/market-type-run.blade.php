@extends('workbench.layout')

@section('title', 'Parallel backtest run')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">PARALLEL BACKTEST / RUN #{{ $run->id }}</span>
            <h1>{{ $run->market_type_name }}</h1>
            <p>{{ $run->configuration['timeframe_name'] }} <span class="result-separator">/</span> {{ $run->configuration['from'] }} — {{ $run->configuration['to'] }}</p>
        </div>
        <div class="heading-actions">
            @if (in_array($run->status, ['completed', 'completed_with_failures'], true) && $counts['succeeded'] > 0)
                <a class="button button-primary" href="{{ route('workbench.tests.market-type-runs.overall', $run) }}" target="_blank" rel="noopener">Overall result ↗</a>
            @endif
            <a class="button button-secondary" href="{{ route('workbench.tests.create') }}">← New test</a>
        </div>
    </div>

    <section class="data-panel results-panel">
        <div class="panel-top">
            <div><span class="eyebrow">EXECUTION STATUS</span><h2>{{ str_replace('_', ' ', strtoupper($run->status)) }}</h2></div>
            <span class="record-count">{{ $run->total_pairs }} PAIRS</span>
        </div>
        <div class="metric-grid">
            @foreach (['pending' => 'PENDING', 'running' => 'RUNNING', 'succeeded' => 'SUCCEEDED', 'failed' => 'FAILED'] as $key => $label)
                <div class="detail-card"><span class="eyebrow">{{ $label }}</span><strong>{{ $counts[$key] }}</strong></div>
            @endforeach
        </div>
        <div class="table-scroll">
            <table class="wide-table">
                <thead><tr><th>PAIR</th><th>STATUS</th><th>STARTED</th><th>COMPLETED</th><th>DETAIL / FAILURE</th><th></th></tr></thead>
                <tbody>
                @forelse ($pairRuns as $pairRun)
                    <tr>
                        <td><span class="instrument-code">{{ $pairRun->pair_name }}</span></td>
                        <td><span class="type-tag">{{ strtoupper($pairRun->status) }}</span></td>
                        <td class="mono">{{ $pairRun->started_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="mono">{{ $pairRun->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td>
                            @if ($pairRun->status === 'succeeded')
                                Backtest result saved
                            @elseif ($pairRun->status === 'failed')
                                {{ $pairRun->failure_message }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($pairRun->status === 'succeeded')
                                <a class="button button-secondary" href="{{ route('workbench.tests.market-type-runs.pairs.show', [$run, $pairRun]) }}" target="_blank" rel="noopener">Detail ↗</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state"><strong>No eligible market pairs</strong><p>Run completed because no pair had price data in the selected timeframe and date range.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($pairRuns->hasPages())
            <div class="pagination-wrap">{{ $pairRuns->links() }}</div>
        @endif
    </section>
    @if (in_array($run->status, ['running', 'pending'], true))
        <script>window.setTimeout(() => window.location.reload(), 5000);</script>
    @endif
@endsection
