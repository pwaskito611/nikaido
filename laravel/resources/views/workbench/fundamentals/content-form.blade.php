@extends('workbench.layout')

@section('title', $content->exists ? 'Edit observation' : 'New observation')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">FUNDAMENTAL / {{ $fundamental->name }}</span><h1>{{ $content->exists ? 'Edit observation' : 'Add observation' }}</h1><p>Values must be valid JSON. The release time controls point-in-time strategy availability.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.fundamentals.show', $fundamental) }}">← Back to fundamental</a>
    </div>
    <form class="form-panel" method="POST" action="{{ $content->exists ? route('workbench.fundamentals.contents.update', [$fundamental, $content]) : route('workbench.fundamentals.contents.store', $fundamental) }}">
        @csrf
        @if ($content->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">INDICATOR OBSERVATION</span><h2>{{ $fundamental->name }}</h2></div></div>
        <label class="field-label" for="code">Stable code <span>*</span></label>
        <input class="text-input" id="code" name="code" value="{{ old('code', $content->code) }}" maxlength="64" required placeholder="CPI">
        <label class="field-label" for="name">Display name <span>*</span></label>
        <input class="text-input" id="name" name="name" value="{{ old('name', $content->name) }}" maxlength="150" required placeholder="Consumer Price Index">
        <label class="field-label" for="datetime">Observation period <span>*</span></label>
        <input class="text-input" id="datetime" name="datetime" type="datetime-local" value="{{ old('datetime', $content->datetime?->format('Y-m-d\TH:i')) }}" required>
        <label class="field-label" for="released_at">Released to market at</label>
        <input class="text-input" id="released_at" name="released_at" type="datetime-local" value="{{ old('released_at', $content->released_at?->format('Y-m-d\TH:i')) }}">
        <small class="field-hint">If release time is blank, the observation is stored but excluded from Strategy Context. It is never assumed available at its observation date.</small>
        <label class="field-label" for="value">Value as JSON <span>*</span></label>
        <textarea class="text-input fundamental-json-input" id="value" name="value" rows="7" required>{{ old('value', $content->exists ? json_encode($content->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : '0') }}</textarea>
        <small class="field-hint">Use a JSON scalar or object, for example <code>13</code>, <code>"positive"</code>, or <code>{"actual": 13, "unit": "%"}</code>.</small>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.fundamentals.show', $fundamental) }}">Cancel</a><button class="button button-primary" type="submit">{{ $content->exists ? 'Save observation' : 'Add observation' }} <span>→</span></button></div>
    </form>
@endsection
