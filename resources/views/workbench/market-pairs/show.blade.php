@extends('workbench.layout')

@section('title', $marketPair->base.'/'.$marketPair->quote)

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">INSTRUMENTS / MARKET PAIR</span><h1 class="instrument-code">{{ $marketPair->base }}<span>/</span>{{ $marketPair->quote }}</h1><p>Instrument details and linked price data.</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.market-pairs.edit', $marketPair) }}">Edit pair <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">MARKET TYPE</span><strong>{{ $marketPair->marketType?->name ?? 'Unclassified' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">PRICE RECORDS</span><strong>{{ $marketPair->prices_count }}</strong><span class="detail-caption">historical candles linked to this pair</span></div>
        <div class="detail-card"><span class="eyebrow">PAIR ID</span><strong class="mono">MP-{{ str_pad((string) $marketPair->id, 4, '0', STR_PAD_LEFT) }}</strong></div>
        <div class="detail-card"><span class="eyebrow">STRATEGY QUANTITY</span><strong>{{ match ($marketPair->quantity_unit ?? 'units') { 'contract' => 'Contracts', 'lot' => 'Lots', default => 'Units / shares / quantity' } }}</strong><span class="detail-caption">{{ ($marketPair->quantity_unit ?? 'units') === 'contract' ? number_format((float) $marketPair->contract_size, 8).' quote-currency units per price point per contract' : ((($marketPair->quantity_unit ?? 'units') === 'lot') ? number_format((float) $marketPair->contract_size, 8).' units per lot' : 'Strategy quantity is used directly') }}</span></div>
    </div>
    <div class="inline-warning">A market pair cannot be deleted while it has price history.</div>
@endsection
