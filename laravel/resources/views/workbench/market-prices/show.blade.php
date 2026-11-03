@extends('workbench.layout')

@section('title', 'Market price')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">HISTORICAL DATA / CANDLE</span><h1 class="mono">{{ $marketPrice->price_date->format('Y-m-d H:i:s') }}</h1><p>{{ $marketPrice->pair?->base }}/{{ $marketPrice->pair?->quote }} <span class="result-separator">/</span> {{ $marketPrice->timeframeType?->name }}</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.market-prices.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.market-prices.edit', $marketPrice) }}">Edit candle <span>↗</span></a></div>
    </div>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">GENERAL PRICE</span><h2>OHLC</h2></div><span class="record-count">ID {{ $marketPrice->id }}</span></div>
        <div class="detail-grid">
            @foreach (['open' => 'Open', 'high' => 'High', 'low' => 'Low', 'close' => 'Close', 'price_at_8' => 'Price at 8'] as $field => $label)
                <div class="detail-card"><span class="eyebrow">{{ strtoupper($label) }}</span><strong class="mono">{{ $marketPrice->{$field} ?? '—' }}</strong></div>
            @endforeach
        </div>
    </section>
    <section class="data-panel results-panel">
        <div class="panel-top"><div><span class="eyebrow">QUOTE PRICE</span><h2>Bid / Ask OHLC</h2></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>TYPE</th><th>OPEN</th><th>HIGH</th><th>LOW</th><th>CLOSE</th></tr></thead>
                <tbody>
                    <tr><td>Bid</td><td class="mono">{{ $marketPrice->bid_open }}</td><td class="mono">{{ $marketPrice->bid_high }}</td><td class="mono">{{ $marketPrice->bid_low }}</td><td class="mono">{{ $marketPrice->bid_close }}</td></tr>
                    <tr><td>Ask</td><td class="mono">{{ $marketPrice->ask_open }}</td><td class="mono">{{ $marketPrice->ask_high }}</td><td class="mono">{{ $marketPrice->ask_low }}</td><td class="mono">{{ $marketPrice->ask_close }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
