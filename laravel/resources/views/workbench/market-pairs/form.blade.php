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
    </script>
@endsection
