@extends('workbench.layout')

@section('title', $timeframe->name)

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">DATA INTERVALS / TIMEFRAME</span><h1 class="mono">{{ $timeframe->name }}</h1><p>Detail interval candle.</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.timeframes.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.timeframes.edit', $timeframe) }}">Edit timeframe <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">TIMEFRAME ID</span><strong class="mono">TF-{{ str_pad((string) $timeframe->id, 4, '0', STR_PAD_LEFT) }}</strong></div>
        <div class="detail-card"><span class="eyebrow">PRICE RECORDS</span><strong>{{ $timeframe->market_prices_count }}</strong><span class="detail-caption">candles using this interval</span></div>
    </div>
    <div class="inline-warning">A timeframe cannot be deleted while it is used by price data.</div>
@endsection
