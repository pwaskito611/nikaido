@extends('workbench.layout')

@section('title', $marketType->name)

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">UNIVERSE / MARKET TYPE</span><h1>{{ $marketType->name }}</h1><p>Market instrument classification details.</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.market-types.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.market-types.edit', $marketType) }}">Edit type <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">MARKET TYPE ID</span><strong class="mono">MT-{{ str_pad((string) $marketType->id, 4, '0', STR_PAD_LEFT) }}</strong></div>
        <div class="detail-card"><span class="eyebrow">LINKED PAIRS</span><strong>{{ $marketType->market_pairs_count }}</strong><span class="detail-caption">instruments in this classification</span></div>
    </div>
    <div class="inline-warning">A market type cannot be deleted while it still has market pairs.</div>
@endsection
