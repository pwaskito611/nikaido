@extends('workbench.layout')

@section('title', 'Run a test')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">RESEARCH / BACKTEST CONFIGURATION</span><h1>Run a test</h1><p>Atur instrumen, rentang data, dan jam aktif strategi.</p></div>
        <span class="step-indicator"><b>01</b><span>CONFIGURE</span><i></i><span>02 RESULTS</span></span>
    </div>
    @if ($timeframes->isEmpty() || ($marketPairs->isEmpty() && $marketTypes->isEmpty()))
        <div class="empty-workspace">
            <span class="empty-icon">⇄</span><h2>{{ $timeframes->isEmpty() ? 'Timeframe belum tersedia' : 'Market type belum tersedia' }}</h2>
            <p>{{ $timeframes->isEmpty() ? 'Tambahkan timeframe untuk memilih interval candle yang akan diuji.' : 'Tambahkan market type dan pair sebelum menjalankan test.' }}</p>
            <div class="heading-actions">
                @if ($marketTypes->isEmpty())
                    <a class="button button-secondary" href="{{ route('workbench.market-types.create') }}">Create market type</a>
                @endif
                @if ($marketPairs->isEmpty())
                    <a class="button button-primary" href="{{ route('workbench.market-pairs.create') }}">Create market pair →</a>
                @else
                    <a class="button button-primary" href="{{ route('workbench.timeframes.create') }}">Create timeframe →</a>
                @endif
            </div>
        </div>
    @else
        <form class="form-panel test-form" method="POST" action="{{ route('workbench.tests.run') }}">
            @csrf
            <div class="panel-top"><div><span class="eyebrow">TEST PARAMETERS</span><h2>Backtest setup</h2></div><span class="card-index">ENGINE / SINGLE OR PARALLEL</span></div>
            <div class="form-section">
                <span class="form-section-number">01</span><div class="form-section-body">
                    <h3>Choose a test type</h3><p>Jalankan satu market pair atau semua pair eligible dalam market type secara paralel.</p>
                    <label class="field-label" for="test_type">Test type <span>*</span></label>
                    <select class="text-input" id="test_type" name="test_type" required>
                        <option value="specific_pair" @selected(old('test_type', 'specific_pair') === 'specific_pair')>Specific pair test</option>
                        <option value="market_type" @selected(old('test_type') === 'market_type')>Parallel market type test</option>
                    </select>
                    <div id="specific-pair-fields">
                        <label class="field-label" for="market_pair_id">Market pair <span>*</span></label>
                        <select class="text-input" id="market_pair_id" name="market_pair_id" required>
                            <option value="">Select a market pair</option>
                            @foreach ($marketPairs as $marketPair)
                                <option value="{{ $marketPair->id }}" @selected(old('market_pair_id') == $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }} · {{ $marketPair->marketType?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="market-type-fields" hidden>
                        <label class="field-label" for="market_type_id">Market type <span>*</span></label>
                        <select class="text-input" id="market_type_id" name="market_type_id">
                            <option value="">Select a market type</option>
                            @foreach ($marketTypes as $marketType)
                                <option value="{{ $marketType->id }}" @selected(old('market_type_id') == $marketType->id)>{{ $marketType->name }}</option>
                            @endforeach
                        </select>
                        <small class="field-hint">Hanya pair dengan candle pada timeframe dan rentang waktu yang dipilih yang akan dijadwalkan.</small>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">02</span><div class="form-section-body">
                    <h3>Choose a timeframe</h3><p>Backtest hanya menggunakan candle dengan interval yang dipilih.</p>
                    <label class="field-label" for="timeframe_type_id">Timeframe <span>*</span></label>
                    <select class="text-input" id="timeframe_type_id" name="timeframe_type_id" required>
                        <option value="">Select a timeframe</option>
                        @foreach ($timeframes as $timeframe)
                            <option value="{{ $timeframe->id }}" @selected(old('timeframe_type_id') == $timeframe->id)>{{ $timeframe->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">03</span><div class="form-section-body">
                    <h3>Set capital and risk</h3><p>Risiko dihitung dari modal berjalan setiap kali posisi baru dibuka.</p>
                    <div class="field-row capital-risk-fields">
                        <div>
                            <label class="field-label" for="initial_capital">Initial capital <span>*</span></label>
                            <input class="text-input" id="initial_capital" name="initial_capital" type="number" min="0.01" step="0.01" value="{{ old('initial_capital', '10000.00') }}" required>
                            <small class="field-hint">Dalam mata uang quote pair, misalnya USD untuk EUR/USD.</small>
                        </div>
                        <div>
                            <label class="field-label" for="risk_per_trade_percent">Risk per trade (%) <span>*</span></label>
                            <input class="text-input" id="risk_per_trade_percent" name="risk_per_trade_percent" type="number" min="0.01" max="100" step="0.01" value="{{ old('risk_per_trade_percent', '1.00') }}" required>
                            <small class="field-hint">Persentase dari modal berjalan saat trade dibuka.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">04</span><div class="form-section-body">
                    <h3>Set the historical window</h3><p>Gunakan waktu lokal aplikasi untuk rentang data yang diuji.</p>
                    <div class="field-row">
                        <div><label class="field-label" for="from">Start date &amp; time <span>*</span></label><input class="text-input" id="from" name="from" type="text" data-flatpickr="datetime" value="{{ old('from', now()->subDays(30)->format('Y-m-d\TH:i')) }}" required></div>
                        <div><label class="field-label" for="to">End date &amp; time <span>*</span></label><input class="text-input" id="to" name="to" type="text" data-flatpickr="datetime" value="{{ old('to', now()->format('Y-m-d\TH:i')) }}" required></div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">05</span><div class="form-section-body">
                    <h3>Configure active schedule</h3><p>Strategi hanya dipanggil dalam jadwal terpilih; posisi tetap dipantau sepanjang data tersedia.</p>
                    <label class="check-row full-day-toggle"><input type="checkbox" id="full_day" name="full_day" value="1" @checked(old('full_day'))><span><strong>Run all day</strong><small>Abaikan batas jam aktif.</small></span></label>
                    <div class="field-row time-fields">
                        <div><label class="field-label" for="active_from">Active from <span>*</span></label><input class="text-input" id="active_from" name="active_from" type="text" data-flatpickr="time" value="{{ old('active_from', '09:00') }}"></div>
                        <div><label class="field-label" for="active_until">Active until <span>*</span></label><input class="text-input" id="active_until" name="active_until" type="text" data-flatpickr="time" value="{{ old('active_until', '17:00') }}"></div>
                    </div>
                    <div class="field-label weekday-label">Exclude weekdays <small>Optional</small></div>
                    <div class="weekday-options">
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                            <label><input type="checkbox" name="excluded_days[]" value="{{ $day }}" @checked(in_array((string) $day, old('excluded_days', []), true))><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">06</span><div class="form-section-body">
                    <h3>Configure execution assumptions</h3>
                    <p>Both simulations default to off, preserving the engine's current execution behavior.</p>
                    <label class="check-row">
                        <input type="checkbox" id="slippage_enabled" name="slippage_enabled" value="1" @checked(old('slippage_enabled'))>
                        <span><strong>Fixed slippage</strong><small>Adverse adjustment is applied once to the executable quote: buy adds slippage; sell subtracts it.</small></span>
                    </label>
                    <div class="field-row">
                        <div>
                            <label class="field-label" for="slippage_pips">Slippage (pips)</label>
                            <input class="text-input" id="slippage_pips" name="slippage_pips" type="number" min="0" step="0.1" value="{{ old('slippage_pips', '1.0') }}">
                            <small class="field-hint">Reference: entry uses ask-open for buy / bid-open for sell; exit uses bid-close for long / ask-close for short, with OHLC fallback. TP uses the target. Spread is not added again. FX pip defaults to 0.0001, or 0.01 when quote is JPY.</small>
                        </div>
                        <div>
                            <label class="field-label" for="slippage_pip_size">Pip size override <small>Optional</small></label>
                            <input class="text-input" id="slippage_pip_size" name="slippage_pip_size" type="number" min="0.00000001" step="any" value="{{ old('slippage_pip_size') }}">
                            <small class="field-hint">Price increment per pip for non-standard instruments. A market-type run applies an override to every pair.</small>
                        </div>
                    </div>
                    <label class="check-row" style="margin-top:16px">
                        <input type="checkbox" id="partial_fill_enabled" name="partial_fill_enabled" value="1" @checked(old('partial_fill_enabled'))>
                        <span><strong>Synthetic partial fills</strong><small>Each attempt fills up to requested quantity × ratio. This is not a liquidity simulation: candle volume and order-book data are not used.</small></span>
                    </label>
                    <div class="field-row">
                        <div>
                            <label class="field-label" for="partial_fill_ratio">Fill ratio (0–1)</label>
                            <input class="text-input" id="partial_fill_ratio" name="partial_fill_ratio" type="number" min="0.000001" max="1" step="0.01" value="{{ old('partial_fill_ratio', '0.50') }}">
                            <small class="field-hint">Applied to entry and exit attempts; each fill is capped by the remaining quantity.</small>
                        </div>
                        <div>
                            <label class="field-label" for="unfilled_order_policy">Unfilled remainder</label>
                            <select class="text-input" id="unfilled_order_policy" name="unfilled_order_policy">
                                <option value="keep" @selected(old('unfilled_order_policy', 'keep') === 'keep')>Keep — retry on the next candle</option>
                                <option value="cancel" @selected(old('unfilled_order_policy') === 'cancel')>Cancel — cancel the remainder</option>
                            </select>
                            <small class="field-hint">Cancel on exit cancels that exit attempt; any still-open position remains exposed and waits for another exit trigger.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions test-submit"><span>Pastikan data harga tersedia untuk pair dan periode yang dipilih.</span><button class="button button-primary" type="submit">Run backtest <span>→</span></button></div>
        </form>
    @endif
    <script>
        const testType = document.getElementById('test_type');
        const pairFields = document.getElementById('specific-pair-fields');
        const marketTypeFields = document.getElementById('market-type-fields');
        const pairSelect = document.getElementById('market_pair_id');
        const marketTypeSelect = document.getElementById('market_type_id');
        if (testType) {
            const updateTestTypeFields = () => {
                const parallel = testType.value === 'market_type';
                pairFields.hidden = parallel;
                marketTypeFields.hidden = !parallel;
                pairSelect.required = !parallel;
                pairSelect.disabled = parallel;
                marketTypeSelect.required = parallel;
                marketTypeSelect.disabled = !parallel;
            };
            testType.addEventListener('change', updateTestTypeFields);
            updateTestTypeFields();
        }
        const slippageToggle = document.getElementById('slippage_enabled');
        const partialFillToggle = document.getElementById('partial_fill_enabled');
        const setOptionalInputs = (toggle, inputIds) => {
            const update = () => inputIds.forEach((id) => {
                document.getElementById(id).disabled = !toggle.checked;
            });
            toggle.addEventListener('change', update);
            update();
        };
        setOptionalInputs(slippageToggle, ['slippage_pips', 'slippage_pip_size']);
        setOptionalInputs(partialFillToggle, ['partial_fill_ratio', 'unfilled_order_policy']);
    </script>
@endsection
