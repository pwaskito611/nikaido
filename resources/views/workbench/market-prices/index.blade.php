@extends('workbench.layout')

@section('title', 'Market prices')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">HISTORICAL DATA / OHLC</span><h1>Market prices</h1><p>Kelola candle OHLC dan kuotasi bid/ask untuk seluruh instrumen.</p></div>
        <a class="button button-primary" href="{{ route('workbench.market-prices.create') }}"><span>＋</span> Add market price</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">CANDLE DATASET</span><h2>All market prices</h2></div><span class="record-count">{{ $marketPrices->total() }} RECORDS</span></div>
        <form method="GET" action="{{ route('workbench.market-prices.index') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;padding:0 20px 18px">
            <label>Market pair
                <select class="text-input" name="pair_id">
                    <option value="">All pairs</option>
                    @foreach ($marketPairs as $marketPair)
                        <option value="{{ $marketPair->id }}" @selected($selectedPairId === $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }}</option>
                    @endforeach
                </select>
            </label>
            <label>Timeframe
                <select class="text-input" name="timeframe_type_id">
                    <option value="">All timeframes</option>
                    @foreach ($timeframes as $timeframe)
                        <option value="{{ $timeframe->id }}" @selected($selectedTimeframeId === $timeframe->id)>{{ $timeframe->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-primary" type="submit">Filter</button>
            <a class="button button-secondary" href="{{ route('workbench.market-prices.index') }}">Clear</a>
        </form>
        <div class="table-scroll">
            <table class="wide-table">
                <thead><tr><th>DATE</th><th>PAIR</th><th>TIMEFRAME</th><th>OPEN</th><th>HIGH</th><th>LOW</th><th>CLOSE</th><th>BID / ASK CLOSE</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($marketPrices as $marketPrice)
                    <tr>
                        <td class="mono"><a class="table-primary" href="{{ route('workbench.market-prices.show', $marketPrice) }}">{{ $marketPrice->price_date->format('Y-m-d H:i:s') }}</a></td>
                        <td><span class="instrument-code">{{ $marketPrice->pair?->base }}/{{ $marketPrice->pair?->quote }}</span></td>
                        <td><span class="type-tag">{{ $marketPrice->timeframeType?->name ?? 'Unknown' }}</span></td>
                        <td class="mono">{{ $marketPrice->open ?? '—' }}</td>
                        <td class="mono">{{ $marketPrice->high ?? '—' }}</td>
                        <td class="mono">{{ $marketPrice->low ?? '—' }}</td>
                        <td class="mono">{{ $marketPrice->close ?? '—' }}</td>
                        <td class="mono">{{ $marketPrice->bid_close }} / {{ $marketPrice->ask_close }}</td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.market-prices.show', $marketPrice) }}">View</a>
                            <a href="{{ route('workbench.market-prices.edit', $marketPrice) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.market-prices.destroy', $marketPrice) }}" onsubmit="return confirm('Hapus data market price ini?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-state"><span>⌁</span><strong>Tidak ada market price</strong><p>Tambahkan candle baru atau ubah filter pair dan timeframe.</p><a href="{{ route('workbench.market-prices.create') }}">Add market price →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($marketPrices->hasPages())<div class="pagination-wrap">{{ $marketPrices->links() }}</div>@endif
    </div>
@endsection
