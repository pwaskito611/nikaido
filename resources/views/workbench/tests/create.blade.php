@extends('workbench.layout')

@section('title', 'Run a test')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">RESEARCH / BACKTEST CONFIGURATION</span><h1>Run a test</h1><p>Atur instrumen, rentang data, dan jam aktif strategi.</p></div>
        <span class="step-indicator"><b>01</b><span>CONFIGURE</span><i></i><span>02 RESULTS</span></span>
    </div>
    @if ($marketPairs->isEmpty() || $timeframes->isEmpty())
        <div class="empty-workspace">
            <span class="empty-icon">⇄</span><h2>{{ $marketPairs->isEmpty() ? 'Market pair belum tersedia' : 'Timeframe belum tersedia' }}</h2>
            <p>{{ $marketPairs->isEmpty() ? 'Tambahkan market type dan market pair sebelum menjalankan test.' : 'Tambahkan timeframe untuk memilih interval candle yang akan diuji.' }}</p>
            <div class="heading-actions">
                @if ($marketPairs->isEmpty())
                    <a class="button button-secondary" href="{{ route('workbench.market-types.create') }}">Create market type</a><a class="button button-primary" href="{{ route('workbench.market-pairs.create') }}">Create market pair →</a>
                @else
                    <a class="button button-primary" href="{{ route('workbench.timeframes.create') }}">Create timeframe →</a>
                @endif
            </div>
        </div>
    @else
        <form class="form-panel test-form" method="POST" action="{{ route('workbench.tests.run') }}">
            @csrf
            <div class="panel-top"><div><span class="eyebrow">TEST PARAMETERS</span><h2>Backtest setup</h2></div><span class="card-index">ENGINE / SPECIFIC PAIR</span></div>
            <div class="form-section">
                <span class="form-section-number">01</span><div class="form-section-body">
                    <h3>Choose an instrument</h3><p>Pilih pair yang memiliki data harga historis.</p>
                    <label class="field-label" for="market_pair_id">Market pair <span>*</span></label>
                    <select class="text-input" id="market_pair_id" name="market_pair_id" required>
                        <option value="">Select a market pair</option>
                        @foreach ($marketPairs as $marketPair)
                            <option value="{{ $marketPair->id }}" @selected(old('market_pair_id') == $marketPair->id)>{{ $marketPair->base }}/{{ $marketPair->quote }} · {{ $marketPair->marketType?->name }}</option>
                        @endforeach
                    </select>
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
                        <div><label class="field-label" for="from">Start date &amp; time <span>*</span></label><input class="text-input" id="from" name="from" type="datetime-local" step="60" value="{{ old('from', now()->subDays(30)->format('Y-m-d\TH:i')) }}" required></div>
                        <div><label class="field-label" for="to">End date &amp; time <span>*</span></label><input class="text-input" id="to" name="to" type="datetime-local" step="60" value="{{ old('to', now()->format('Y-m-d\TH:i')) }}" required></div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">05</span><div class="form-section-body">
                    <h3>Configure active schedule</h3><p>Strategi hanya dipanggil dalam jadwal terpilih; posisi tetap dipantau sepanjang data tersedia.</p>
                    <label class="check-row full-day-toggle"><input type="checkbox" id="full_day" name="full_day" value="1" @checked(old('full_day'))><span><strong>Run all day</strong><small>Abaikan batas jam aktif.</small></span></label>
                    <div class="field-row time-fields">
                        <div><label class="field-label" for="active_from">Active from <span>*</span></label><input class="text-input" id="active_from" name="active_from" type="time" value="{{ old('active_from', '09:00') }}"></div>
                        <div><label class="field-label" for="active_until">Active until <span>*</span></label><input class="text-input" id="active_until" name="active_until" type="time" value="{{ old('active_until', '17:00') }}"></div>
                    </div>
                    <div class="field-label weekday-label">Exclude weekdays <small>Optional</small></div>
                    <div class="weekday-options">
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                            <label><input type="checkbox" name="excluded_days[]" value="{{ $day }}" @checked(in_array((string) $day, old('excluded_days', []), true))><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="form-actions test-submit"><span>Pastikan data harga tersedia untuk pair dan periode yang dipilih.</span><button class="button button-primary" type="submit">Run backtest <span>→</span></button></div>
        </form>
    @endif
@endsection
