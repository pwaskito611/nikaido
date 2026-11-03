@extends('workbench.layout')

@section('title', $fundamental->name)

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">MARKET CONTEXT / FUNDAMENTAL</span><h1>{{ $fundamental->name }}</h1><p>{{ $fundamental->description ?: 'Historical economic or company observations.' }}</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.fundamentals.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.fundamentals.edit', $fundamental) }}">Edit entity <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">CATEGORY</span><strong>{{ $fundamental->category ?: '—' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">OBSERVATIONS</span><strong>{{ $fundamental->contents_count }}</strong></div>
        <div class="detail-card"><span class="eyebrow">RELATED PAIRS</span><strong>{{ $fundamental->market_pairs_count }}</strong></div>
    </div>
    <section class="form-panel fundamental-section">
        <div class="panel-top"><div><span class="eyebrow">DATA DEPENDENCIES</span><h2>Related market pairs</h2></div></div>
        @forelse ($fundamental->marketPairs as $marketPair)
            <a class="tag" href="{{ route('workbench.market-pairs.show', $marketPair) }}">{{ $marketPair->base }}/{{ $marketPair->quote }}</a>
        @empty
            <p class="field-hint">No market pairs are linked. This entity is not included in pair backtests.</p>
        @endforelse
    </section>
    <div class="data-panel fundamental-section">
        <div class="panel-top"><div><span class="eyebrow">HISTORICAL DATA</span><h2>Indicator observations</h2></div><a class="button button-primary" href="{{ route('workbench.fundamentals.contents.create', $fundamental) }}">＋ Add observation</a></div>
        @php($contentGroups = $contents->getCollection()->groupBy('code'))
        @forelse ($contentGroups as $code => $observations)
            <section class="fundamental-indicator">
                <h3>{{ $observations->first()->name }} <span class="mono">{{ $code }}</span></h3>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>OBSERVATION PERIOD</th><th>VALUE</th><th>RELEASED AT</th><th class="align-right">ACTIONS</th></tr></thead>
                        <tbody>
                        @foreach ($observations as $observation)
                            <tr>
                                <td>{{ $observation->datetime->format('F Y-m-d') }}</td>
                                <td><code>{{ json_encode($observation->value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) }}</code></td>
                                <td>{{ $observation->released_at?->format('Y-m-d H:i:s') ?: 'Not released — unavailable to strategy' }}</td>
                                <td class="align-right actions">
                                    <a href="{{ route('workbench.fundamentals.contents.edit', [$fundamental, $observation]) }}">Edit</a>
                                    <form method="POST" action="{{ route('workbench.fundamentals.contents.destroy', [$fundamental, $observation]) }}" onsubmit="return confirm('Delete this observation?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="empty-state"><strong>No observations yet</strong><p>Add a dated value. Set its release time to make it available to backtests.</p><a href="{{ route('workbench.fundamentals.contents.create', $fundamental) }}">Add observation →</a></div>
        @endforelse
        @if ($contents->hasPages())<div class="pagination-wrap">{{ $contents->links() }}</div>@endif
    </div>
@endsection
