@extends('workbench.layout')

@section('title', 'Market pairs')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">INSTRUMENTS / MARKET UNIVERSE</span><h1>Market pairs</h1><p>Instruments and price pairs used by the engine.</p></div>
        <a class="button button-primary" href="{{ route('workbench.market-pairs.create') }}"><span>＋</span> Add market pair</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">AVAILABLE INSTRUMENTS</span><h2>All market pairs</h2></div><span class="record-count">{{ $marketPairs->total() }} RECORDS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>INSTRUMENT</th><th>MARKET TYPE</th><th>PRICE RECORDS</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($marketPairs as $marketPair)
                    <tr>
                        <td><a class="table-primary instrument-code" href="{{ route('workbench.market-pairs.show', $marketPair) }}">{{ $marketPair->base }}<span>/</span>{{ $marketPair->quote }}</a><span class="table-meta mono">MP-{{ str_pad((string) $marketPair->id, 4, '0', STR_PAD_LEFT) }}</span></td>
                        <td><span class="type-tag">{{ $marketPair->marketType?->name ?? 'Unclassified' }}</span></td>
                        <td><span class="count-pill">{{ $marketPair->prices_count }} records</span></td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.market-pairs.show', $marketPair) }}">View</a>
                            <a href="{{ route('workbench.market-pairs.edit', $marketPair) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.market-pairs.destroy', $marketPair) }}" onsubmit="return confirm('Delete this market pair?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state"><span>⇄</span><strong>No market pairs yet</strong><p>Add a pair after creating a market type.</p><a href="{{ route('workbench.market-pairs.create') }}">Add market pair →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($marketPairs->hasPages())<div class="pagination-wrap">{{ $marketPairs->links() }}</div>@endif
    </div>
@endsection
