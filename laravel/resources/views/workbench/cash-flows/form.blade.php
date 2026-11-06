@extends('workbench.layout')

@section('title', $cashFlow->exists ? 'Edit contract adjustment' : 'New contract adjustment')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">CFD CONTRACT ADJUSTMENT</span><h1>{{ $cashFlow->exists ? 'Edit adjustment' : 'Add adjustment' }}</h1><p>Record the USD dividend adjustment per underlying unit. Availability time prevents look-ahead in strategy data.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.cash-flows.index') }}">← Back to adjustments</a>
    </div>
    <form class="form-panel" method="POST" action="{{ $cashFlow->exists ? route('workbench.cash-flows.update', $cashFlow) : route('workbench.cash-flows.store') }}">
        @csrf
        @if ($cashFlow->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">CONTRACT ADJUSTMENT</span><h2>Adjustment details</h2></div></div>
        <label class="field-label" for="market_pair_id">Market pair <span>*</span></label>
        <select class="text-input" id="market_pair_id" name="market_pair_id" required>
            <option value="">Select pair</option>
            @foreach ($marketPairs as $marketPair)
                <option value="{{ $marketPair->id }}" @selected((string) old('market_pair_id', $cashFlow->market_pair_id) === (string) $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }}</option>
            @endforeach
        </select>
        <label class="field-label" for="event_type">Event type <span>*</span></label>
        <select class="text-input" id="event_type" name="event_type" required>
            <option value="dividend_adjustment" @selected(old('event_type', $cashFlow->event_type ?: 'dividend_adjustment') === 'dividend_adjustment')>Dividend / contract adjustment</option>
        </select>
        <label class="field-label" for="amount_per_unit">USD adjustment per underlying unit <span>*</span></label>
        <input class="text-input" id="amount_per_unit" name="amount_per_unit" type="number" min="0.000000000001" step="0.000000000001" value="{{ old('amount_per_unit', $cashFlow->amount_per_unit) }}" required>
        <label class="field-label" for="status">Status <span>*</span></label>
        <select class="text-input" id="status" name="status" required>
            @foreach (['announced', 'confirmed', 'paid', 'cancelled'] as $status)
                <option value="{{ $status }}" @selected(old('status', $cashFlow->status ?: 'announced') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @foreach (['announced_at' => 'Announced at', 'available_at' => 'Available to strategy at', 'ex_date' => 'Ex-date / entitlement date', 'record_date' => 'Record date', 'payable_at' => 'Payment date'] as $field => $label)
            <label class="field-label" for="{{ $field }}">{{ $label }}{{ $field === 'available_at' ? ' *' : '' }}</label>
            <input class="text-input" id="{{ $field }}" name="{{ $field }}" type="datetime-local" value="{{ old($field, $cashFlow->$field?->format('Y-m-d\TH:i')) }}" @required($field === 'available_at')>
        @endforeach
        <small class="field-hint">Only expose an event to a backtest from the time the information was actually available. Leave event dates blank when the source does not provide them.</small>
        <label class="field-label" for="source">Data source</label>
        <input class="text-input" id="source" name="source" value="{{ old('source', $cashFlow->source) }}" maxlength="150">
        <label class="field-label" for="external_id">External event ID</label>
        <input class="text-input" id="external_id" name="external_id" value="{{ old('external_id', $cashFlow->external_id) }}" maxlength="191">
        <small class="field-hint">An external ID should be stable across feed updates and is unique within its market pair.</small>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.cash-flows.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $cashFlow->exists ? 'Save event' : 'Add event' }} <span>→</span></button></div>
    </form>
@endsection
