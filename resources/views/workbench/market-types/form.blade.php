@extends('workbench.layout')

@section('title', $marketType->exists ? 'Edit market type' : 'New market type')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">UNIVERSE / CLASSIFICATION</span><h1>{{ $marketType->exists ? 'Edit market type' : 'New market type' }}</h1><p>Market type digunakan untuk mengelompokkan market pair.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.market-types.index') }}">← Back to list</a>
    </div>
    <form class="form-panel" method="POST" action="{{ $marketType->exists ? route('workbench.market-types.update', $marketType) : route('workbench.market-types.store') }}">
        @csrf
        @if ($marketType->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">TYPE DETAILS</span><h2>Classification</h2></div><span class="card-index">REQUIRED FIELDS *</span></div>
        <label class="field-label" for="name">Market type name <span>*</span></label>
        <input class="text-input" id="name" name="name" value="{{ old('name', $marketType->name) }}" maxlength="50" placeholder="e.g. Forex, Crypto, Equities" required autofocus>
        <div class="field-hint">Nama harus unik dan maksimal 50 karakter.</div>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.market-types.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $marketType->exists ? 'Save changes' : 'Create market type' }} <span>→</span></button></div>
    </form>
@endsection
