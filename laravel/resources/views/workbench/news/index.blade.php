@extends('workbench.layout')

@section('title', 'News')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">MARKET CONTEXT / POINT-IN-TIME DATA</span><h1>News</h1><p>Manage news articles and their pair-specific, time-aware impact assessments.</p></div>
        <a class="button button-primary" href="{{ route('workbench.news.create') }}"><span>＋</span> Add news</a>
    </div>
    <div class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">NEWS ITEMS</span><h2>All news</h2></div><span class="record-count">{{ $newsItems->total() }} RECORDS</span></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ARTICLE</th><th>SOURCE</th><th>PUBLISHED / AVAILABLE</th><th>PAIR IMPACT</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($newsItems as $newsItem)
                    <tr>
                        <td><span class="table-primary">{{ $newsItem->title }}</span><span class="table-meta">{{ \Illuminate\Support\Str::limit($newsItem->content, 130) }}</span></td>
                        <td>{{ $newsItem->source }}{{ $newsItem->external_id ? ' · '.$newsItem->external_id : '' }}</td>
                        <td>{{ $newsItem->published_at->format('Y-m-d H:i') }}<span class="table-meta">Known at {{ $newsItem->available_at->format('Y-m-d H:i') }}</span></td>
                        <td>
                            @foreach ($newsItem->impacts as $impact)
                                <span class="count-pill">{{ $impact->marketPair->base }}/{{ $impact->marketPair->quote }} · {{ number_format((float) $impact->impact_score, 2) }}</span>
                            @endforeach
                        </td>
                        <td class="align-right actions">
                            <a href="{{ route('workbench.news.edit', $newsItem) }}">Edit</a>
                            <form method="POST" action="{{ route('workbench.news.destroy', $newsItem) }}" onsubmit="return confirm('Delete this news item and its pair impacts?')">@csrf @method('DELETE')<button class="link-danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state"><span>◷</span><strong>No news yet</strong><p>Add an article or import news through a pair-scoped API receiver.</p><a href="{{ route('workbench.news.create') }}">Add news →</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($newsItems->hasPages())<div class="pagination-wrap">{{ $newsItems->links() }}</div>@endif
    </div>
@endsection
