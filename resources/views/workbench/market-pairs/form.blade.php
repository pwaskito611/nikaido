@extends('workbench.layout')

@section('title', $marketPair->exists ? 'Edit market pair' : 'New market pair')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">INSTRUMENTS / MARKET UNIVERSE</span><h1>{{ $marketPair->exists ? 'Edit market pair' : 'New market pair' }}</h1><p>Tentukan klasifikasi dan simbol untuk instrumen ini.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">← Back to list</a>
    </div>
    @if ($marketTypes->isEmpty())
        <div class="notice notice-error">Buat market type terlebih dahulu sebelum menambahkan pair. <a href="{{ route('workbench.market-types.create') }}">Create market type →</a></div>
    @endif
    <form class="form-panel" method="POST" action="{{ $marketPair->exists ? route('workbench.market-pairs.update', $marketPair) : route('workbench.market-pairs.store') }}">
        @csrf
        @if ($marketPair->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">INSTRUMENT DETAILS</span><h2>Market pair</h2></div><span class="card-index">REQUIRED FIELDS *</span></div>
        <label class="field-label" for="market_type_id">Market type <span>*</span></label>
        <select class="text-input" id="market_type_id" name="market_type_id" required>
            <option value="">Select a market type</option>
            @foreach ($marketTypes as $marketType)
                <option value="{{ $marketType->id }}" @selected(old('market_type_id', $marketPair->market_type_id) == $marketType->id)>{{ $marketType->name }}</option>
            @endforeach
        </select>
        <div class="field-row">
            <div><label class="field-label" for="base">Base currency / asset <span>*</span></label><input class="text-input" id="base" name="base" value="{{ old('base', $marketPair->base) }}" maxlength="10" placeholder="EUR" required></div>
            <div class="pair-slash">/</div>
            <div><label class="field-label" for="quote">Quote currency / asset <span>*</span></label><input class="text-input" id="quote" name="quote" value="{{ old('quote', $marketPair->quote) }}" maxlength="10" placeholder="USD" required></div>
        </div>
        <div class="field-hint">Simbol akan disimpan dalam huruf kapital. Kombinasi pair harus unik di setiap market type.</div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">Cancel</a><button class="button button-primary" type="submit" @disabled($marketTypes->isEmpty())>{{ $marketPair->exists ? 'Save changes' : 'Create market pair' }} <span>→</span></button></div>
    </form>
@endsection
