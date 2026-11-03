@extends('workbench.layout')

@section('title', 'Market types')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">UNIVERSE / CLASSIFICATION</span><h1>Market types</h1><p>Manage asset categories to organize market pairs.</p></div>
        <a class="button button-primary" href="{{ route('workbench.market-types.create') }}"><span>＋</span> Add market type</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">MARKET CONFIGURATION</span><h2>All market types</h2></div><span class="record-count">{{ $marketTypes->total() }} RECORDS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>TYPE</th><th>PAIRS</th><th>CREATED ID</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($marketTypes as $marketType)
                    <tr>
                        <td><a class="table-primary" href="{{ route('workbench.market-types.show', $marketType) }}">{{ $marketType->name }}</a><span class="table-meta">Market classification</span></td>
                        <td><span class="count-pill">{{ $marketType->market_pairs_count }} pairs</span></td>
                        <td class="mono">MT-{{ str_pad((string) $marketType->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.market-types.show', $marketType) }}">View</a>
                            <a href="{{ route('workbench.market-types.edit', $marketType) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.market-types.destroy', $marketType) }}" onsubmit="return confirm('Delete this market type?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state"><span>◈</span><strong>No market types yet</strong><p>Create your first market type to organize pairs.</p><a href="{{ route('workbench.market-types.create') }}">Add market type →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($marketTypes->hasPages())<div class="pagination-wrap">{{ $marketTypes->links() }}</div>@endif
    </div>
@endsection
