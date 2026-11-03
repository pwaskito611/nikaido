@extends('workbench.layout')

@section('title', $marketPrice->exists ? 'Edit market price' : 'New market price')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">HISTORICAL DATA / OHLC</span><h1>{{ $marketPrice->exists ? 'Edit market price' : 'New market price' }}</h1><p>Set the candle time and bid/ask OHLC prices.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.market-prices.index') }}">← Back to list</a>
    </div>
    @if ($marketPairs->isEmpty() || $timeframes->isEmpty())
        <div class="notice notice-error">
            Create a market pair and timeframe before adding prices.
            @if ($marketPairs->isEmpty())<a href="{{ route('workbench.market-pairs.create') }}">Create market pair →</a>@endif
            @if ($timeframes->isEmpty())<a href="{{ route('workbench.timeframes.create') }}">Create timeframe →</a>@endif
        </div>
    @endif
    <form class="form-panel" method="POST" action="{{ $marketPrice->exists ? route('workbench.market-prices.update', $marketPrice) : route('workbench.market-prices.store') }}">
        @csrf
        @if ($marketPrice->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">CANDLE DETAILS</span><h2>Market price</h2></div><span class="card-index">REQUIRED FIELDS *</span></div>
        <div class="field-row">
            <div>
                <label class="field-label" for="pair_id">Market pair <span>*</span></label>
                <select class="text-input" id="pair_id" name="pair_id" required>
                    <option value="">Select a pair</option>
                    @foreach ($marketPairs as $marketPair)
                        <option value="{{ $marketPair->id }}" @selected(old('pair_id', $marketPrice->pair_id) == $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label" for="timeframe_type_id">Timeframe <span>*</span></label>
                <select class="text-input" id="timeframe_type_id" name="timeframe_type_id" required>
                    <option value="">Select a timeframe</option>
                    @foreach ($timeframes as $timeframe)
                        <option value="{{ $timeframe->id }}" @selected(old('timeframe_type_id', $marketPrice->timeframe_type_id) == $timeframe->id)>{{ $timeframe->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="field-label" for="price_date">Candle date and time <span>*</span></label>
        <input class="text-input" id="price_date" name="price_date" type="text" data-flatpickr="datetime" value="{{ old('price_date', $marketPrice->price_date?->format('Y-m-d\TH:i')) }}" required>

        <h3 class="eyebrow" style="margin:24px 0 12px">GENERAL OHLC</h3>
        <div class="field-row">
            @foreach (['open' => 'Open', 'high' => 'High', 'low' => 'Low', 'close' => 'Close'] as $field => $label)
                <div><label class="field-label" for="{{ $field }}">{{ $label }}{{ $field === 'close' ? ' *' : '' }}</label><input class="text-input" id="{{ $field }}" name="{{ $field }}" type="number" min="0.000001" step="0.000001" value="{{ old($field, $marketPrice->{$field}) }}" @required($field === 'close')></div>
            @endforeach
        </div>
        <h3 class="eyebrow" style="margin:24px 0 12px">BID OHLC <span class="muted">(optional)</span></h3>
        <div class="field-row">
            @foreach (['bid_open' => 'Bid open', 'bid_high' => 'Bid high', 'bid_low' => 'Bid low', 'bid_close' => 'Bid close'] as $field => $label)
                <div><label class="field-label" for="{{ $field }}">{{ $label }}</label><input class="text-input" id="{{ $field }}" name="{{ $field }}" type="number" min="0.000001" step="0.000001" value="{{ old($field, $marketPrice->{$field}) }}"></div>
            @endforeach
        </div>
        <h3 class="eyebrow" style="margin:24px 0 12px">ASK OHLC <span class="muted">(optional)</span></h3>
        <div class="field-row">
            @foreach (['ask_open' => 'Ask open', 'ask_high' => 'Ask high', 'ask_low' => 'Ask low', 'ask_close' => 'Ask close'] as $field => $label)
                <div><label class="field-label" for="{{ $field }}">{{ $label }}</label><input class="text-input" id="{{ $field }}" name="{{ $field }}" type="number" min="0.000001" step="0.000001" value="{{ old($field, $marketPrice->{$field}) }}"></div>
            @endforeach
        </div>
        <label class="field-label" for="price_at_8">Price at 8 <span class="muted">(optional)</span></label>
        <input class="text-input" id="price_at_8" name="price_at_8" type="number" min="0.000001" step="0.000001" value="{{ old('price_at_8', $marketPrice->price_at_8) }}">
        <div class="field-hint">The pair, timeframe, and timestamp must be unique. Prices support up to 6 decimal places. Bid/ask OHLC, general OHLC (except close), and price at 8 are optional.</div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.market-prices.index') }}">Cancel</a><button class="button button-primary" type="submit" @disabled($marketPairs->isEmpty() || $timeframes->isEmpty())>{{ $marketPrice->exists ? 'Save changes' : 'Create market price' }} <span>→</span></button></div>
    </form>
@endsection
