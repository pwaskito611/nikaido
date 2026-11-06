@extends('workbench.layout')

@section('title', 'CFD contract adjustments')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">CFD MARKET DATA / CONTRACT ADJUSTMENTS</span><h1>Contract adjustments</h1><p>Record dated dividend adjustments in USD per underlying unit. These are not spot-asset distributions.</p></div>
        <a class="button button-primary" href="{{ route('workbench.cash-flows.create') }}">＋ Add adjustment</a>
    </div>

    <section class="data-panel">
        <div class="panel-top"><div><span class="eyebrow">HISTORICAL ADJUSTMENTS</span><h2>{{ $cashFlows->total() }} adjustments</h2></div></div>
        <form method="GET" class="filter-form">
            <label class="field-label" for="market_pair_id">Market pair</label>
            <select class="text-input" id="market_pair_id" name="market_pair_id">
                <option value="">All pairs</option>
                @foreach ($marketPairs as $marketPair)
                    <option value="{{ $marketPair->id }}" @selected($selectedMarketPairId === $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }}</option>
                @endforeach
            </select>
            <button class="button button-secondary" type="submit">Filter</button>
        </form>
        <div class="table-scroll">
            <table>
                <thead><tr><th>AVAILABLE AT</th><th>PAIR</th><th>TYPE</th><th>USD / UNIT</th><th>EX-DATE</th><th>PAYABLE</th><th>STATUS</th><th class="align-right">ACTIONS</th></tr></thead>
                <tbody>
                @forelse ($cashFlows as $cashFlow)
                    <tr>
                        <td>{{ $cashFlow->available_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $cashFlow->marketPair->base }}/{{ $cashFlow->marketPair->quote }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($cashFlow->event_type)) }}</td>
                        <td class="mono">{{ $cashFlow->amount_per_unit }}</td>
                        <td>{{ $cashFlow->ex_date?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td>{{ $cashFlow->payable_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td><span class="data-import-status">{{ strtoupper($cashFlow->status) }}</span></td>
                        <td class="align-right actions"><a href="{{ route('workbench.cash-flows.show', $cashFlow) }}">View</a><a href="{{ route('workbench.cash-flows.edit', $cashFlow) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-table">No contract adjustments found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($cashFlows->hasPages())<div class="pagination-wrap">{{ $cashFlows->links() }}</div>@endif
    </section>
@endsection
