@extends('workbench.layout')

@section('title', $fundamental->exists ? 'Edit fundamental' : 'New fundamental')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">MARKET CONTEXT / FUNDAMENTALS</span><h1>{{ $fundamental->exists ? 'Edit fundamental' : 'New fundamental' }}</h1><p>Define an economic or company data entity and its market-pair dependencies.</p></div>
        <a class="button button-secondary" href="{{ $fundamental->exists ? route('workbench.fundamentals.show', $fundamental) : route('workbench.fundamentals.index') }}">← Back</a>
    </div>
    <form class="form-panel" method="POST" action="{{ $fundamental->exists ? route('workbench.fundamentals.update', $fundamental) : route('workbench.fundamentals.store') }}">
        @csrf
        @if ($fundamental->exists) @method('PUT') @endif
        <div class="panel-top"><div><span class="eyebrow">ENTITY DETAILS</span><h2>Fundamental</h2></div></div>
        <label class="field-label" for="name">Name <span>*</span></label>
        <input class="text-input" id="name" name="name" value="{{ old('name', $fundamental->name) }}" maxlength="150" required placeholder="United Kingdom Economy">
        <label class="field-label" for="category">Category</label>
        <input class="text-input" id="category" name="category" value="{{ old('category', $fundamental->category) }}" maxlength="100" placeholder="Economy or Company">
        <label class="field-label" for="description">Description</label>
        <textarea class="text-input" id="description" name="description" rows="3">{{ old('description', $fundamental->description) }}</textarea>
        <div class="field-row" style="margin-top:20px">
            <div>
                <span class="field-label">Related market pairs</span>
                <input type="hidden" name="pair_ids_present" value="1">
                @php
                    $selectedPairIds = old(
                        'pair_ids',
                        $fundamental->exists ? $fundamental->marketPairs->modelKeys() : [],
                    );
                    $selectedPairIds = array_map('strval', $selectedPairIds);
                @endphp
                <label class="field-label" for="fundamental-related-pair-search">Search pairs</label>
                <input
                    class="text-input related-pair-search"
                    id="fundamental-related-pair-search"
                    type="search"
                    placeholder="Search by pair or market type..."
                    autocomplete="off"
                    aria-controls="fundamental-related-pair-list"
                >
                <div class="fundamental-pair-list" id="fundamental-related-pair-list">
                    @foreach ($marketPairs as $marketPair)
                        <div data-fundamental-pair-row>
                            <label>
                                <input type="checkbox" name="pair_ids[]" value="{{ $marketPair->id }}" @checked(in_array((string) $marketPair->id, $selectedPairIds, true))>
                                {{ $marketPair->base }}/{{ $marketPair->quote }} · {{ $marketPair->marketType?->name ?? 'Unclassified' }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="related-pair-empty" data-fundamental-pair-empty hidden>No market pairs match your search.</p>
                <small class="field-hint">Only fundamentals linked to a pair are loaded for that pair's backtest. The relationship does not start another backtest.</small>
                @error('pair_ids') <div class="field-error">{{ $message }}</div> @enderror
                @error('pair_ids.*') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ $fundamental->exists ? route('workbench.fundamentals.show', $fundamental) : route('workbench.fundamentals.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $fundamental->exists ? 'Save changes' : 'Create fundamental' }} <span>→</span></button></div>
    </form>
    <script>
        const fundamentalPairSearch = document.getElementById('fundamental-related-pair-search');
        const fundamentalPairRows = [...document.querySelectorAll('[data-fundamental-pair-row]')];
        const fundamentalPairEmpty = document.querySelector('[data-fundamental-pair-empty]');
        fundamentalPairSearch.addEventListener('input', () => {
            const query = fundamentalPairSearch.value.trim().toLocaleLowerCase();
            let visibleRows = 0;

            fundamentalPairRows.forEach((row) => {
                const matches = row.textContent.toLocaleLowerCase().includes(query);
                row.hidden = !matches;
                visibleRows += Number(matches);
            });

            fundamentalPairEmpty.hidden = visibleRows > 0;
        });
    </script>
@endsection
