@extends('workbench.layout')

@section('title', $marketPair->exists ? 'Edit market pair' : 'New market pair')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">INSTRUMENTS / MARKET UNIVERSE</span><h1>{{ $marketPair->exists ? 'Edit market pair' : 'New market pair' }}</h1><p>Set the classification and symbols for this instrument.</p></div>
        <a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">← Back to list</a>
    </div>
    @if ($marketTypes->isEmpty())
        <div class="notice notice-error">Create a market type before adding a pair. <a href="{{ route('workbench.market-types.create') }}">Create market type →</a></div>
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
        <label class="field-label" for="quantity_unit">Strategy quantity unit <span>*</span></label>
        <select class="text-input" id="quantity_unit" name="quantity_unit" required>
            <option value="units" @selected(old('quantity_unit', $marketPair->quantity_unit ?? 'units') === 'units')>Unit / share / quantity</option>
            <option value="contract" @selected(old('quantity_unit', $marketPair->quantity_unit ?? 'units') === 'contract')>Contract (e.g. futures)</option>
            <option value="lot" @selected(old('quantity_unit', $marketPair->quantity_unit ?? 'units') === 'lot')>Lots</option>
        </select>
        <div id="quantity-multiplier-field" @if (old('quantity_unit', $marketPair->quantity_unit ?? 'units') === 'units') hidden @endif>
            <label class="field-label" for="contract_size"><span id="quantity-multiplier-label">Quantity multiplier</span> <span>*</span></label>
            <input class="text-input" id="contract_size" name="contract_size" type="number" min="0.00000001" step="0.00000001" value="{{ old('contract_size', $marketPair->contract_size) }}">
            <small class="field-hint" id="quantity-multiplier-hint">Configure the instrument's actual multiplier.</small>
        </div>
        <small class="field-hint">The strategy supplies the quantity for each entry. Unit/share/quantity passes through directly; contracts retain contract quantity and apply the multiplier to P&amp;L and risk; lots convert to underlying units using units per lot.</small>
        <div class="field-row" style="margin-top:20px">
            <div>
                <span class="field-label">Related Market Pairs</span>
                <input type="hidden" name="related_market_pairs_present" value="1">
                @php
                    $savedRelatedPairs = $marketPair->exists
                        ? $marketPair->relatedPairs->keyBy('id')
                        : collect();
                    $selectedRelatedPairIds = old(
                        'related_market_pair_ids',
                        old('related_market_pairs_present') ? [] : $savedRelatedPairs->keys()->all(),
                    );
                    $selectedRelatedPairIds = array_map('strval', $selectedRelatedPairIds);
                @endphp
                <label class="field-label" for="related-market-pair-search">Search pairs</label>
                <input
                    class="text-input related-pair-search"
                    id="related-market-pair-search"
                    type="search"
                    placeholder="Search by pair or market type..."
                    autocomplete="off"
                    aria-controls="related-market-pair-list"
                >
                <div class="related-pair-list">
                    <div class="related-pair-list-inner" id="related-market-pair-list">
                    @foreach ($availableMarketPairs as $relatedPair)
                        @php
                            $isSelected = in_array((string) $relatedPair->id, $selectedRelatedPairIds, true);
                            $savedTimeframeId = $savedRelatedPairs->get($relatedPair->id)?->pivot?->timeframe_type_id;
                            $selectedTimeframeId = old("related_timeframes.{$relatedPair->id}", $savedTimeframeId);
                            $isPrimaryPair = $marketPair->exists && $relatedPair->id === $marketPair->id;
                            $selectedRelatedPairIndex = array_search((string) $relatedPair->id, $selectedRelatedPairIds, true);
                        @endphp
                        <div class="related-pair-row" data-related-pair-row>
                            <label>
                                <input
                                    type="checkbox"
                                    name="related_market_pair_ids[]"
                                    value="{{ $relatedPair->id }}"
                                    @checked($isSelected)
                                    data-related-pair-checkbox
                                >
                                {{ $relatedPair->base }}/{{ $relatedPair->quote }} · {{ $relatedPair->marketType?->name ?? 'Unclassified' }}@if ($isPrimaryPair) (same pair, alternate timeframe)@endif
                            </label>
                            <select
                                class="text-input"
                                name="related_timeframes[{{ $relatedPair->id }}]"
                                aria-label="Timeframe for {{ $relatedPair->base }}/{{ $relatedPair->quote }}"
                                @disabled(! $isSelected)
                                data-related-pair-timeframe
                            >
                                @unless ($isPrimaryPair)
                                    <option value="">Use backtest timeframe</option>
                                @endunless
                                @foreach ($availableTimeframes as $timeframe)
                                    <option value="{{ $timeframe->id }}" @selected((string) $selectedTimeframeId === (string) $timeframe->id)>
                                        {{ $timeframe->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($selectedRelatedPairIndex !== false)
                                @error('related_market_pair_ids.' . $selectedRelatedPairIndex)
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            @endif
                            @error('related_timeframes.' . $relatedPair->id)
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                    </div>
                    <p class="related-pair-empty" data-related-pair-empty hidden>No market pairs match your search.</p>
                </div>
                <small class="field-hint">Choose the timeframe independently for each related series. “Use backtest timeframe” preserves the default. Related pairs are directional data dependencies only; they do not run separate backtests.</small>
                @error('related_market_pair_ids') <div class="field-error">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="field-hint">Symbols are stored in uppercase. Each pair must be unique within its market type.</div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('workbench.market-pairs.index') }}">Cancel</a><button class="button button-primary" type="submit" @disabled($marketTypes->isEmpty())>{{ $marketPair->exists ? 'Save changes' : 'Create market pair' }} <span>→</span></button></div>
    </form>
    <script>
        const quantityUnit = document.getElementById('quantity_unit');
        const multiplierField = document.getElementById('quantity-multiplier-field');
        const multiplierInput = document.getElementById('contract_size');
        const multiplierLabel = document.getElementById('quantity-multiplier-label');
        const multiplierHint = document.getElementById('quantity-multiplier-hint');
        const updateQuantityUnitFields = () => {
            const usesMultiplier = quantityUnit.value !== 'units';
            const usesContracts = quantityUnit.value === 'contract';
            multiplierField.hidden = !usesMultiplier;
            multiplierInput.required = usesMultiplier;
            multiplierLabel.textContent = usesContracts ? 'Currency value per price point per contract' : 'Units per lot';
            multiplierHint.textContent = usesContracts
                ? 'For futures, enter the contract point value in the pair quote currency (for example, 50 currency units per index point).'
                : 'For lots, enter the underlying units represented by one lot (for example, 100,000 base units for a standard FX lot).';
        };
        quantityUnit.addEventListener('change', updateQuantityUnitFields);
        updateQuantityUnitFields();

        document.querySelectorAll('[data-related-pair-checkbox]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                checkbox.closest('.related-pair-row')
                    .querySelector('[data-related-pair-timeframe]')
                    .disabled = !checkbox.checked;
            });
        });

        const relatedPairSearch = document.getElementById('related-market-pair-search');
        const relatedPairRows = [...document.querySelectorAll('[data-related-pair-row]')];
        const relatedPairEmpty = document.querySelector('[data-related-pair-empty]');
        relatedPairSearch.addEventListener('input', () => {
            const query = relatedPairSearch.value.trim().toLocaleLowerCase();
            let visibleRows = 0;

            relatedPairRows.forEach((row) => {
                const matches = row.textContent.toLocaleLowerCase().includes(query);
                row.hidden = !matches;
                visibleRows += Number(matches);
            });

            relatedPairEmpty.hidden = visibleRows > 0;
        });
    </script>
@endsection
