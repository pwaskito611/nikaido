@extends('workbench.layout')

@section('title', $timeframe->exists ? 'Edit timeframe' : 'New timeframe')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">DATA INTERVALS / CANDLE TYPES</span><h1>{{ $timeframe->exists ? 'Edit timeframe' : 'New timeframe' }}</h1><p>Use an interval label recognized by your candle data source.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.timeframes.index') }}">← Back to list</a>
    </div>
    <form class="form-panel" method="POST" action="{{ $timeframe->exists ? route('workbench.timeframes.update', $timeframe) : route('workbench.timeframes.store') }}">
        @csrf
        @if ($timeframe->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">TIMEFRAME DETAILS</span><h2>Candle interval</h2></div><span class="card-index">REQUIRED FIELDS *</span></div>
        <label class="field-label" for="name">Timeframe name <span>*</span></label>
        <input class="text-input" id="name" name="name" value="{{ old('name', $timeframe->name) }}" maxlength="50" placeholder="e.g. 15m, 4h, 1d" required autofocus>
        <div class="field-hint">Name must be unique and no longer than 50 characters.</div>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.timeframes.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $timeframe->exists ? 'Save changes' : 'Create timeframe' }} <span>→</span></button></div>
    </form>
@endsection
