@extends('workbench.layout')

@section('title', 'Timeframes')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">DATA INTERVALS / CANDLE TYPES</span><h1>Timeframes</h1><p>Manage candle intervals available for backtesting.</p></div>
        <a class="button button-primary" href="{{ route('workbench.timeframes.create') }}"><span>＋</span> Add timeframe</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">ENGINE CONFIGURATION</span><h2>All timeframes</h2></div><span class="record-count">{{ $timeframes->total() }} RECORDS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>TIMEFRAME</th><th>PRICE RECORDS</th><th>REFERENCE ID</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($timeframes as $timeframe)
                    <tr>
                        <td><a class="table-primary mono" href="{{ route('workbench.timeframes.show', $timeframe) }}">{{ $timeframe->name }}</a><span class="table-meta">Candle interval</span></td>
                        <td><span class="count-pill">{{ $timeframe->market_prices_count }} records</span></td>
                        <td class="mono">TF-{{ str_pad((string) $timeframe->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.timeframes.show', $timeframe) }}">View</a>
                            <a href="{{ route('workbench.timeframes.edit', $timeframe) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.timeframes.destroy', $timeframe) }}" onsubmit="return confirm('Delete this timeframe?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state"><span>◷</span><strong>No timeframes yet</strong><p>Create an interval to select when running a backtest.</p><a href="{{ route('workbench.timeframes.create') }}">Add timeframe →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($timeframes->hasPages())<div class="pagination-wrap">{{ $timeframes->links() }}</div>@endif
    </div>
@endsection
