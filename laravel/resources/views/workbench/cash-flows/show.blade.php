@extends('workbench.layout')

@section('title', 'Contract adjustment')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">CFD CONTRACT ADJUSTMENT / {{ $cashFlow->marketPair->base }}/{{ $cashFlow->marketPair->quote }}</span><h1>Dividend adjustment</h1><p>{{ $cashFlow->amount_per_unit }} USD per underlying unit · {{ ucfirst($cashFlow->status) }}</p></div>
        <div class="heading-actions"><a class="button button-secondary" href="{{ route('workbench.cash-flows.index') }}">← Back</a><a class="button button-primary" href="{{ route('workbench.cash-flows.edit', $cashFlow) }}">Edit event <span>↗</span></a></div>
    </div>
    <div class="detail-grid">
        <div class="detail-card"><span class="eyebrow">AVAILABLE AT</span><strong>{{ $cashFlow->available_at->format('Y-m-d H:i:s') }}</strong></div>
        <div class="detail-card"><span class="eyebrow">EX-DATE</span><strong>{{ $cashFlow->ex_date?->format('Y-m-d H:i:s') ?? '—' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">PAYABLE AT</span><strong>{{ $cashFlow->payable_at?->format('Y-m-d H:i:s') ?? '—' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">ANNOUNCED AT</span><strong>{{ $cashFlow->announced_at?->format('Y-m-d H:i:s') ?? '—' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">RECORD DATE</span><strong>{{ $cashFlow->record_date?->format('Y-m-d H:i:s') ?? '—' }}</strong></div>
        <div class="detail-card"><span class="eyebrow">SOURCE / EXTERNAL ID</span><strong>{{ $cashFlow->source ?: '—' }}{{ $cashFlow->external_id ? ' · '.$cashFlow->external_id : '' }}</strong></div>
    </div>
    <form method="POST" action="{{ route('workbench.cash-flows.destroy', $cashFlow) }}" onsubmit="return confirm('Delete this contract adjustment?')">
        @csrf @method('DELETE')
        <button class="button button-secondary" type="submit">Delete event</button>
    </form>
@endsection
