@extends('workbench.layout')

@section('title', $marketPair->base.'/'.$marketPair->quote)

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">INSTRUMENTS / MARKET PAIR</span><h1 class="instrument-code">{{ $marketPair->base }}<span>/</span>{{ $marketPair->quote }}</h1><p>Detail instrumen dan data harga yang terhubung.</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.market-pairs.edit', $marketPair) }}">Edit pair <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">MARKET TYPE</span><strong>{{ $marketPair->marketType?->name ?? 'Unclassified' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">PRICE RECORDS</span><strong>{{ $marketPair->prices_count }}</strong><span class="detail-caption">historical candles linked to this pair</span></div>
        <div class="detail-card"><span class="eyebrow">PAIR ID</span><strong class="mono">MP-{{ str_pad((string) $marketPair->id, 4, '0', STR_PAD_LEFT) }}</strong></div>
    </div>
    <div class="inline-warning">Market pair yang memiliki riwayat harga tidak dapat dihapus.</div>
@endsection
