@extends('workbench.layout')

@section('title', $newsItem->exists ? 'Edit news' : 'New news')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">MARKET CONTEXT / NEWS</span><h1>{{ $newsItem->exists ? 'Edit news' : 'Add news' }}</h1><p>Keep publication time separate from the time the strategy could first know this information.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.news.index') }}">← Back</a>
    </div>
    @if ($errors->any())
        <div class="form-panel"><div class="field-error">{{ $errors->first() }}</div></div>
    @endif
    <form class="form-panel" method="POST" action="{{ $newsItem->exists ? route('workbench.news.update', $newsItem) : route('workbench.news.store') }}">
        @csrf
        @if ($newsItem->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">ARTICLE AND PAIR IMPACT</span><h2>News details</h2></div></div>
        <label class="field-label" for="market_pair_id">Market pair <span>*</span></label>
        <select class="text-input" id="market_pair_id" name="market_pair_id" required>
            <option value="">Select pair</option>
            @foreach ($marketPairs as $marketPair)
                <option value="{{ $marketPair->id }}" @selected((string) old('market_pair_id', $impact?->market_pair_id) === (string) $marketPair->id)>
                    {{ $marketPair->base }}/{{ $marketPair->quote }} · {{ $marketPair->marketType?->name ?? 'Unclassified' }}
                </option>
            @endforeach
        </select>
        @error('market_pair_id') <div class="field-error">{{ $message }}</div> @enderror
        <div class="field-row">
            <div><label class="field-label" for="source">Source <span>*</span></label><input class="text-input" id="source" name="source" value="{{ old('source', $newsItem->source) }}" maxlength="100" required></div>
            <div><label class="field-label" for="external_id">External ID</label><input class="text-input" id="external_id" name="external_id" value="{{ old('external_id', $newsItem->external_id) }}" maxlength="191"></div>
        </div>
        <label class="field-label" for="title">Title <span>*</span></label>
        <input class="text-input" id="title" name="title" value="{{ old('title', $newsItem->title) }}" maxlength="500" required>
        <label class="field-label" for="content">Content <span>*</span></label>
        <textarea class="text-input" id="content" name="content" rows="6" maxlength="1000000" required>{{ old('content', $newsItem->content) }}</textarea>
        <div class="field-row">
            <div><label class="field-label" for="published_at">Published at <span>*</span></label><input class="text-input" id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $newsItem->published_at?->format('Y-m-d\TH:i')) }}" required></div>
            <div><label class="field-label" for="available_at">Available at <span>*</span></label><input class="text-input" id="available_at" name="available_at" type="datetime-local" value="{{ old('available_at', $newsItem->available_at?->format('Y-m-d\TH:i')) }}" required><small>Strategy cannot access this item before this time.</small></div>
        </div>
        <label class="field-label" for="language">Language</label>
        <input class="text-input" id="language" name="language" value="{{ old('language', $newsItem->language) }}" maxlength="10" placeholder="en">
        <div class="field-row">
            <div><label class="field-label" for="relevance_score">Relevance score (0–1) <span>*</span></label><input class="text-input" id="relevance_score" name="relevance_score" type="number" step="0.00001" min="0" max="1" value="{{ old('relevance_score', $impact?->relevance_score ?? 1) }}" required></div>
            <div><label class="field-label" for="impact_score">Impact score (-1–1) <span>*</span></label><input class="text-input" id="impact_score" name="impact_score" type="number" step="0.00001" min="-1" max="1" value="{{ old('impact_score', $impact?->impact_score ?? 0) }}" required></div>
        </div>
        <div class="field-row">
            <div><label class="field-label" for="impact_lag_seconds">Impact lag (seconds) <span>*</span></label><input class="text-input" id="impact_lag_seconds" name="impact_lag_seconds" type="number" min="0" max="31536000" value="{{ old('impact_lag_seconds', $impact?->impact_lag_seconds ?? 0) }}" required></div>
            <div><label class="field-label" for="impact_duration_seconds">Impact duration (seconds) <span>*</span></label><input class="text-input" id="impact_duration_seconds" name="impact_duration_seconds" type="number" min="1" max="31536000" value="{{ old('impact_duration_seconds', $impact?->impact_duration_seconds ?? 3600) }}" required></div>
        </div>
        <div class="field-row">
            <div><label class="field-label" for="confidence_score">Confidence (0–1) <span>*</span></label><input class="text-input" id="confidence_score" name="confidence_score" type="number" step="0.00001" min="0" max="1" value="{{ old('confidence_score', $impact?->confidence_score ?? 1) }}" required></div>
            <div><label class="field-label" for="assessment_method">Assessment method <span>*</span></label><select class="text-input" id="assessment_method" name="assessment_method" required>@foreach (['manual' => 'Manual', 'model' => 'Model', 'provider' => 'Provider'] as $value => $label)<option value="{{ $value }}" @selected(old('assessment_method', $impact?->assessment_method ?? 'manual') === $value)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <small>Impact score is directional for this pair: negative is bearish, positive is bullish, and zero means neutral/unknown. Keep assessments point-in-time safe: do not use price reactions that happen after the strategy's decision time.</small>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.news.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $newsItem->exists ? 'Save changes' : 'Create news item' }} <span>→</span></button></div>
    </form>
@endsection
