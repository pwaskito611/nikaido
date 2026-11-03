@extends('workbench.layout')

@section('title', 'Fundamentals')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">MARKET CONTEXT / REFERENCE DATA</span><h1>Fundamentals</h1><p>Manage economic and company indicators for strategy inputs.</p></div>
        <a class="button button-primary" href="{{ route('workbench.fundamentals.create') }}"><span>＋</span> Add fundamental</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">FUNDAMENTAL ENTITIES</span><h2>All fundamentals</h2></div><span class="record-count">{{ $fundamentals->total() }} RECORDS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ENTITY</th><th>CATEGORY</th><th>INDICATORS</th><th>MARKET PAIRS</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($fundamentals as $fundamental)
                    <tr>
                        <td><a class="table-primary" href="{{ route('workbench.fundamentals.show', $fundamental) }}">{{ $fundamental->name }}</a><span class="table-meta">{{ $fundamental->description }}</span></td>
                        <td>{{ $fundamental->category ?: '—' }}</td>
                        <td><span class="count-pill">{{ $fundamental->contents_count }} observations</span></td>
                        <td><span class="count-pill">{{ $fundamental->market_pairs_count }} pairs</span></td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.fundamentals.show', $fundamental) }}">View</a>
                            <a href="{{ route('workbench.fundamentals.edit', $fundamental) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.fundamentals.destroy', $fundamental) }}" onsubmit="return confirm('Delete this fundamental and its observations?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state"><span>◈</span><strong>No fundamentals yet</strong><p>Create an economic or company entity, then add its historical observations.</p><a href="{{ route('workbench.fundamentals.create') }}">Add fundamental →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($fundamentals->hasPages())<div class="pagination-wrap">{{ $fundamentals->links() }}</div>@endif
    </div>
@endsection
