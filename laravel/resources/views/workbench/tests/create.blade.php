@extends('workbench.layout')

@section('title', 'Run a test')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">RESEARCH / BACKTEST CONFIGURATION</span><h1>Run a test</h1><p>Configure the instrument, data range, and strategy schedule.</p></div>
        <span class="step-indicator"><b>01</b><span>CONFIGURE</span><i></i><span>02 RESULTS</span></span>
    </div>
    @if ($timeframes->isEmpty() || ($marketPairs->isEmpty() && $marketTypes->isEmpty()))
        <div class="empty-workspace">
            <span class="empty-icon">⇄</span><h2>{{ $timeframes->isEmpty() ? 'No timeframes available' : 'No market types available' }}</h2>
            <p>{{ $timeframes->isEmpty() ? 'Add a timeframe to choose the candle interval for testing.' : 'Add a market type and pair before running a test.' }}</p>
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
                    <h3>Choose a test type</h3><p>Run a single market pair or all eligible pairs in a market type in parallel.</p>
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
                        <small class="field-hint">Pairs created before CFD contract profiles were added must be updated in Market Pairs before they can be backtested.</small>
                    </div>
                    <div id="market-type-fields" hidden>
                        <label class="field-label" for="market_type_id">Market type <span>*</span></label>
                        <select class="text-input" id="market_type_id" name="market_type_id">
                            <option value="">Select a market type</option>
                            @foreach ($marketTypes as $marketType)
                                <option value="{{ $marketType->id }}" @selected(old('market_type_id') == $marketType->id)>{{ $marketType->name }}</option>
                            @endforeach
                        </select>
                        <div id="capital-mode-field">
                            <label class="field-label" for="capital_mode">Capital management <span>*</span></label>
                            <select class="text-input" id="capital_mode" name="capital_mode">
                                <option value="shared" @selected(old('capital_mode', 'shared') === 'shared')>Shared portfolio capital</option>
                                <option value="separate" @selected(old('capital_mode') === 'separate')>Separate capital per pair</option>
                            </select>
                            <small class="field-hint">Shared mode coordinates risk chronologically across pairs. Separate mode gives every pair its own starting capital; the overall total uses the sum of those accounts.</small>
                        </div>
                        <small class="field-hint">Only pairs with candles in the selected timeframe and date range will be scheduled.</small>
                        <label class="field-label pair-exclusion-heading">Exclude pairs <small>Optional</small></label>
                        <small class="field-hint">Selected pairs will be excluded from this parallel test.</small>
                        <div class="pair-exclusion-options" id="pair-exclusion-options">
                            @foreach ($marketPairs as $marketPair)
                                <label data-excluded-pair-option data-market-type-id="{{ $marketPair->market_type_id }}" hidden>
                                    <input type="checkbox" name="excluded_pair_ids[]" value="{{ $marketPair->id }}" @checked(in_array((string) $marketPair->id, old('excluded_pair_ids', []), true)) disabled>
                                    <span>{{ $marketPair->base }}/{{ $marketPair->quote }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="field-hint" id="pair-exclusion-empty">Select a market type to see its pairs.</small>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">02</span><div class="form-section-body">
                    <h3>Choose a timeframe</h3><p>The backtest uses only candles at the selected interval.</p>
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
                    <h3>Set starting capital</h3><p>Enter USD capital. Strategy supplies quantity using each instrument's tick-value profile; the engine enforces quantity steps and initial margin.</p>
                    <div>
                        <label class="field-label" for="initial_capital">Initial capital <span>*</span></label>
                        <input class="text-input" id="initial_capital" name="initial_capital" type="number" min="0.01" step="0.01" value="{{ old('initial_capital', '10000.00') }}" required>
                        <small class="field-hint" id="initial-capital-hint">All capital and P/L values are USD. Pair prices must already be normalized; Nikaido does not convert currencies.</small>
                    </div>
                    <label class="check-row">
                        <input type="checkbox" id="include_cash_flows" name="include_cash_flows" value="1" @checked(old('include_cash_flows'))>
                        <span><strong>Include CFD dividend adjustments</strong><small>Apply confirmed/paid contract adjustments on ex-date for positions held before that candle; short positions receive the opposite adjustment. Disabled by default.</small></span>
                    </label>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">04</span><div class="form-section-body">
                    <h3>Set the historical window</h3><p>Use the application's local time for the test date range.</p>
                    <div class="field-row">
                        <div><label class="field-label" for="from">Start date &amp; time <span>*</span></label><input class="text-input" id="from" name="from" type="text" data-flatpickr="datetime" value="{{ old('from', now()->subDays(30)->format('Y-m-d\TH:i')) }}" required></div>
                        <div><label class="field-label" for="to">End date &amp; time <span>*</span></label><input class="text-input" id="to" name="to" type="text" data-flatpickr="datetime" value="{{ old('to', now()->format('Y-m-d\TH:i')) }}" required></div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <span class="form-section-number">05</span><div class="form-section-body">
                    <h3>Configure active schedule</h3><p>The strategy is called only during the selected schedule; open positions are monitored whenever data is available.</p>
                    <label class="check-row full-day-toggle"><input type="checkbox" id="full_day" name="full_day" value="1" @checked(old('full_day'))><span><strong>Run all day</strong><small>Ignore active-time boundaries.</small></span></label>
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
                        <span><strong>Slippage</strong><small>Adverse slippage is applied to actual fills: buy adds slippage; sell subtracts it. Existing runs default to fixed pips.</small></span>
                    </label>
                    <div class="field-row">
                        <div>
                            <label class="field-label" for="slippage_mode">Slippage model</label>
                            <select class="text-input" id="slippage_mode" name="slippage_mode">
                                <option value="fixed" @selected(old('slippage_mode', 'fixed') === 'fixed')>Fixed</option>
                                <option value="dynamic" @selected(old('slippage_mode') === 'dynamic')>Dynamic</option>
                                <option value="random" @selected(old('slippage_mode') === 'random')>Random</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="slippage_direction">Slippage direction</label>
                            <select class="text-input" id="slippage_direction" name="slippage_direction">
                                <option value="adverse_only" @selected(old('slippage_direction', 'adverse_only') === 'adverse_only')>Adverse only</option>
                                <option value="random" @selected(old('slippage_direction') === 'random')>Random (adverse or favorable)</option>
                            </select>
                            <small class="field-hint">Random direction selects adverse or favorable with equal probability for every fill. It uses the configured random seed.</small>
                        </div>
                        <div>
                            <label class="field-label" for="slippage_unit">Slippage unit</label>
                            <select class="text-input" id="slippage_unit" name="slippage_unit">
                                <option value="pips" selected>Pips</option>
                            </select>
                            <small class="field-hint">All model inputs are expressed in pips. FX pip defaults to 0.0001, or 0.01 when quote is JPY.</small>
                        </div>
                        <div>
                            <label class="field-label" for="slippage_pip_size">Pip size override <small>Optional</small></label>
                            <input class="text-input" id="slippage_pip_size" name="slippage_pip_size" type="number" min="0.00000001" step="any" value="{{ old('slippage_pip_size') }}">
                            <small class="field-hint">Price increment per pip for non-standard instruments. Spread and candle range are converted to pips using this value.</small>
                        </div>
                        <div id="slippage-fixed-fields">
                            <label class="field-label" for="fixed_slippage">Fixed slippage (pips)</label>
                            <input class="text-input" id="fixed_slippage" name="fixed_slippage" type="number" min="0" max="1000000000" step="0.1" value="{{ old('fixed_slippage', old('slippage_pips', '1.0')) }}">
                            <small class="field-hint">The same adverse amount is used for each actual entry or exit fill.</small>
                        </div>
                        <div id="slippage-dynamic-fields" hidden>
                            <label class="field-label" for="base_slippage">Base slippage (pips)</label>
                            <input class="text-input" id="base_slippage" name="base_slippage" type="number" min="0" max="1000000000" step="any" value="{{ old('base_slippage', '0') }}">
                            <label class="field-label" for="volatility_factor">Volatility factor</label>
                            <input class="text-input" id="volatility_factor" name="volatility_factor" type="number" min="0" max="1000000000" step="any" value="{{ old('volatility_factor', '0') }}">
                            <small class="field-hint">Multiplies the candle high-low range converted to pips.</small>
                            <label class="field-label" for="volume_impact_factor">Volume impact factor (pips)</label>
                            <input class="text-input" id="volume_impact_factor" name="volume_impact_factor" type="number" min="0" max="1000000000" step="any" value="{{ old('volume_impact_factor', '0') }}">
                            <small class="field-hint">Multiplies sqrt(filled quantity / explicitly typed traded volume). Requires positive candle traded volume when nonzero.</small>
                            <label class="field-label" for="spread_factor">Spread factor</label>
                            <input class="text-input" id="spread_factor" name="spread_factor" type="number" min="0" max="1000000000" step="any" value="{{ old('spread_factor', '0') }}">
                            <small class="field-hint">Multiplies the bid-ask spread converted to pips. Uses open quotes for entry, close quotes for exit; spread is not added again to the execution reference.</small>
                            <label class="field-label" for="dynamic_maximum_slippage">Maximum slippage (pips)</label>
                            <input class="text-input" id="dynamic_maximum_slippage" name="maximum_slippage" type="number" min="0" max="1000000000" step="any" value="{{ old('maximum_slippage', '10') }}">
                        </div>
                        <div id="slippage-random-fields" hidden>
                            <label class="field-label" for="minimum_slippage">Minimum slippage (pips)</label>
                            <input class="text-input" id="minimum_slippage" name="minimum_slippage" type="number" min="0" max="1000000000" step="any" value="{{ old('minimum_slippage', '0') }}">
                            <label class="field-label" for="random_maximum_slippage">Maximum slippage (pips)</label>
                            <input class="text-input" id="random_maximum_slippage" name="maximum_slippage" type="number" min="0" max="1000000000" step="any" value="{{ old('maximum_slippage', '2') }}">
                            <label class="field-label" for="distribution">Distribution</label>
                            <select class="text-input" id="distribution" name="distribution">
                                <option value="uniform" @selected(old('distribution', 'uniform') === 'uniform')>Uniform</option>
                                <option value="normal" @selected(old('distribution') === 'normal')>Normal (clipped to bounds)</option>
                            </select>
                            <div id="slippage-normal-fields" hidden>
                                <label class="field-label" for="mean">Mean (pips)</label>
                                <input class="text-input" id="mean" name="mean" type="number" min="0" max="1000000000" step="any" value="{{ old('mean', '1') }}">
                                <label class="field-label" for="standard_deviation">Standard deviation (pips)</label>
                                <input class="text-input" id="standard_deviation" name="standard_deviation" type="number" min="0" max="1000000000" step="any" value="{{ old('standard_deviation', '0.25') }}">
                            </div>
                            <label class="field-label" for="random_seed">Random seed <small>Optional</small></label>
                            <input class="text-input" id="random_seed" name="random_seed" type="number" min="0" max="2147483647" step="1" value="{{ old('random_seed') }}">
                            <small class="field-hint">A seed reproduces the same slippage sequence for the same execution sequence.</small>
                        </div>
                        <div class="field-hint">Reference: entry uses ask-open for buy / bid-open for sell; exit uses bid-close for long / ask-close for short, with OHLC fallback. Take-profit uses the target. No spread is applied twice.</div>
                    </div>
                    <label class="check-row" style="margin-top:16px">
                        <input type="checkbox" id="partial_fill_enabled" name="partial_fill_enabled" value="1" @checked(old('partial_fill_enabled'))>
                        <span><strong>Partial fills</strong><small>Choose a fixed fill ratio or cap each candle's fills by explicitly marked traded volume. Missing volume never means unlimited liquidity.</small></span>
                    </label>
                    <div class="field-row">
                        <div>
                            <label class="field-label" for="partial_fill_mode">Fill mode</label>
                            <select class="text-input" id="partial_fill_mode" name="partial_fill_mode">
                                <option value="fixed" @selected(old('partial_fill_mode', 'fixed') === 'fixed')>Fixed partial fill</option>
                                <option value="volume" @selected(old('partial_fill_mode') === 'volume')>Volume-based partial fill</option>
                            </select>
                            <small class="field-hint">Fixed mode preserves the configured per-attempt quantity ratio.</small>
                        </div>
                        <div id="partial-fill-fixed-fields">
                            <label class="field-label" for="partial_fill_ratio">Fill ratio (0–1)</label>
                            <input class="text-input" id="partial_fill_ratio" name="partial_fill_ratio" type="number" min="0.000001" max="1" step="0.01" value="{{ old('partial_fill_ratio', '0.50') }}">
                            <small class="field-hint">Applied to entry and exit attempts; each fill is capped by the remaining quantity.</small>
                        </div>
                        <div id="partial-fill-volume-fields" hidden>
                            <label class="field-label" for="max_participation_rate">Maximum participation rate (0–1)</label>
                            <input class="text-input" id="max_participation_rate" name="max_participation_rate" type="number" min="0" max="1" step="0.01" value="{{ old('max_participation_rate', '0.10') }}">
                            <small class="field-hint">For example, 0.10 means at most 10% of the candle's traded volume.</small>
                            <label class="field-label" for="volume_source">Volume source</label>
                            <select class="text-input" id="volume_source" name="volume_source">
                                <option value="traded_volume" @selected(old('volume_source', 'traded_volume') === 'traded_volume')>MarketPrice candle volume marked as traded</option>
                            </select>
                            <small class="field-hint">Tick volume is not accepted as traded quantity and is never substituted.</small>
                            <label class="field-label" for="execution_volume_period">Execution volume period</label>
                            <select class="text-input" id="execution_volume_period" name="execution_volume_period">
                                <option value="candle" @selected(old('execution_volume_period', 'candle') === 'candle')>Each candle in the selected timeframe</option>
                            </select>
                            <small class="field-hint">All entry and exit fills on a candle share its one volume participation budget.</small>
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
            <div class="form-section">
                <span class="form-section-number">07</span><div class="form-section-body">
                    <h3>Commission and overnight financing</h3>
                    <p>Configure the main CFD costs in USD. Bid/ask prices model spread when available; the OHLC fallback adds no separate spread charge. Slippage remains controlled above.</p>
                    <label class="check-row">
                        <input type="checkbox" id="trade_costs_enabled" name="trade_costs[enabled]" value="1" @checked(old('trade_costs.enabled'))>
                        <span><strong>Enable commission and overnight financing</strong><small>Only configured costs are deducted from P/L. Both default to off.</small></span>
                    </label>
                    @php
                        $tradeCostComponents = [
                            'commission' => ['Commission', ['per_order' => 'Fixed amount per order side', 'transaction_value_percent' => 'Percentage of transaction value', 'per_quantity' => 'Amount per unit / lot / contract']],
                            'financing_fee' => ['Overnight financing', ['daily_notional_percent' => 'Percentage of entry notional per 24 hours']],
                            'exchange_fee' => ['Exchange fee', ['per_order' => 'Fixed amount per order side', 'transaction_value_percent' => 'Percentage of transaction value', 'per_quantity' => 'Amount per unit / lot / contract']],
                            'clearing_fee' => ['Clearing fee', ['per_order' => 'Fixed amount per order side', 'transaction_value_percent' => 'Percentage of transaction value', 'per_quantity' => 'Amount per unit / lot / contract']],
                            'regulatory_fee' => ['Regulatory fee', ['per_order' => 'Fixed amount per order side', 'transaction_value_percent' => 'Percentage of transaction value', 'per_quantity' => 'Amount per unit / lot / contract']],
                            'transaction_tax' => ['Transaction tax', ['per_order' => 'Fixed amount per order side', 'transaction_value_percent' => 'Percentage of transaction value', 'per_quantity' => 'Amount per unit / lot / contract']],
                        ];
                    @endphp
                    @foreach ($tradeCostComponents as $component => [$label, $methods])
                        @php
                            $holdingCost = $component === 'financing_fee';
                            $methodField = "trade_costs.{$component}.method";
                            $rateField = "trade_costs.{$component}.rate";
                            $componentEnabled = "trade_costs.{$component}.enabled";
                        @endphp
                        @if ($component === 'exchange_fee')
                            <details class="trade-cost-advanced">
                                <summary>Advanced transaction costs</summary>
                                <p class="field-hint">Optional exchange, clearing, regulatory, and transaction charges.</p>
                        @endif
                        <div class="trade-cost-component">
                            <label class="check-row">
                                <input class="trade-cost-toggle" type="checkbox" id="trade_cost_{{ $component }}_enabled" name="trade_costs[{{ $component }}][enabled]" value="1" @checked(old($componentEnabled))>
                                <span><strong>{{ $label }}</strong><small>{{ $holdingCost ? 'Applied to long and short positions when enabled.' : 'Applied independently on entry and exit fills.' }}</small></span>
                            </label>
                            <div class="field-row trade-cost-fields" id="trade_cost_{{ $component }}_fields" hidden>
                                <div>
                                    <label class="field-label" for="trade_cost_{{ $component }}_method">Calculation method</label>
                                    <select class="text-input trade-cost-method" id="trade_cost_{{ $component }}_method" name="trade_costs[{{ $component }}][method]">
                                        @foreach ($methods as $method => $methodLabel)
                                            <option value="{{ $method }}" @selected(old($methodField, $holdingCost ? 'daily_notional_percent' : ($component === 'commission' ? 'transaction_value_percent' : 'per_order')) === $method)>{{ $methodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @unless ($holdingCost)
                                    <div>
                                        <label class="field-label" for="trade_cost_{{ $component }}_applies_to">Charge side</label>
                                        <select class="text-input" id="trade_cost_{{ $component }}_applies_to" name="trade_costs[{{ $component }}][applies_to]">
                                            @if ($component === 'transaction_tax')
                                                <option value="" @selected(old("trade_costs.{$component}.applies_to") === null)>Choose applicable side</option>
                                            @endif
                                            <option value="both" @selected(old("trade_costs.{$component}.applies_to", $component === 'transaction_tax' ? '' : 'both') === 'both')>Entry and exit</option>
                                            <option value="entry" @selected(old("trade_costs.{$component}.applies_to") === 'entry')>Entry only</option>
                                            <option value="exit" @selected(old("trade_costs.{$component}.applies_to") === 'exit')>Exit only</option>
                                        </select>
                                    </div>
                                @endunless
                                <div>
                                    <label class="field-label" for="trade_cost_{{ $component }}_rate">{{ $holdingCost ? 'Rate (% per 24 hours)' : 'Rate' }}</label>
                                    <input class="text-input" id="trade_cost_{{ $component }}_rate" name="trade_costs[{{ $component }}][rate]" type="number" min="0" step="any" value="{{ old($rateField, '0') }}">
                                    <small class="field-hint">{{ $holdingCost ? 'Applied to entry-fill notional for each elapsed 24-hour period and charged on exit; use this single setting for overnight financing.' : 'Percentage rates use executed USD notional. Per-quantity rates use the selected strategy unit (units, lots, or contracts); fixed amounts are USD per order side.' }}</small>
                                </div>
                            </div>
                        </div>
                        @if ($component === 'transaction_tax')
                            </details>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="form-actions test-submit"><span>Make sure price data is available for the selected pair and date range.</span><button class="button button-primary" type="submit">Run backtest <span>→</span></button></div>
        </form>
    @endif
    <script>
        const testType = document.getElementById('test_type');
        const pairFields = document.getElementById('specific-pair-fields');
        const marketTypeFields = document.getElementById('market-type-fields');
        const pairSelect = document.getElementById('market_pair_id');
        const marketTypeSelect = document.getElementById('market_type_id');
        const capitalModeField = document.getElementById('capital-mode-field');
        const capitalModeSelect = document.getElementById('capital_mode');
        const initialCapitalHint = document.getElementById('initial-capital-hint');
        const exclusionOptions = [...document.querySelectorAll('[data-excluded-pair-option]')];
        const exclusionEmpty = document.getElementById('pair-exclusion-empty');
        const updatePairExclusionOptions = () => {
            const selectedMarketType = marketTypeSelect.value;
            let visibleOptions = 0;
            for (const option of exclusionOptions) {
                const matchesMarketType = selectedMarketType !== '' && option.dataset.marketTypeId === selectedMarketType;
                option.hidden = !matchesMarketType;
                const checkbox = option.querySelector('input');
                checkbox.disabled = !matchesMarketType || testType.value !== 'market_type';
                if (matchesMarketType) {
                    visibleOptions += 1;
                }
            }
            exclusionEmpty.hidden = visibleOptions > 0;
            exclusionEmpty.textContent = selectedMarketType === ''
                ? 'Select a market type to see its pairs.'
                : 'This market type has no pairs yet.';
        };
        if (testType) {
            const updateTestTypeFields = () => {
                const parallel = testType.value === 'market_type';
                pairFields.hidden = parallel;
                marketTypeFields.hidden = !parallel;
                pairSelect.required = !parallel;
                pairSelect.disabled = parallel;
                marketTypeSelect.required = parallel;
                marketTypeSelect.disabled = !parallel;
                capitalModeField.hidden = !parallel;
                capitalModeSelect.required = parallel;
                capitalModeSelect.disabled = !parallel;
                initialCapitalHint.textContent = !parallel
                    ? 'All capital and P/L values are USD. Pair prices must already be normalized; Nikaido does not convert currencies.'
                    : capitalModeSelect.value === 'shared'
                        ? 'This is one shared USD risk and initial-margin budget. Pair prices and P/L values are combined without FX conversion.'
                        : 'Each successful pair starts with this USD amount. Overall capital is the sum across pairs; no FX conversion is performed.';
                updatePairExclusionOptions();
            };
            testType.addEventListener('change', updateTestTypeFields);
            updateTestTypeFields();
            marketTypeSelect.addEventListener('change', updatePairExclusionOptions);
            capitalModeSelect.addEventListener('change', updateTestTypeFields);
        }
        const slippageToggle = document.getElementById('slippage_enabled');
        const partialFillToggle = document.getElementById('partial_fill_enabled');
        const slippageMode = document.getElementById('slippage_mode');
        const slippageModeFields = {
            fixed: document.getElementById('slippage-fixed-fields'),
            dynamic: document.getElementById('slippage-dynamic-fields'),
            random: document.getElementById('slippage-random-fields'),
        };
        const normalSlippageFields = document.getElementById('slippage-normal-fields');
        const updateSlippageFields = () => {
            slippageMode.disabled = !slippageToggle.checked;
            document.getElementById('slippage_direction').disabled = !slippageToggle.checked;
            document.getElementById('slippage_unit').disabled = !slippageToggle.checked;
            document.getElementById('slippage_pip_size').disabled = !slippageToggle.checked;
            for (const [mode, fields] of Object.entries(slippageModeFields)) {
                fields.hidden = mode !== slippageMode.value;
                for (const input of fields.querySelectorAll('input, select')) {
                    input.disabled = !slippageToggle.checked || fields.hidden;
                }
            }
            normalSlippageFields.hidden = document.getElementById('distribution').value !== 'normal';
            for (const input of normalSlippageFields.querySelectorAll('input, select')) {
                input.disabled = !slippageToggle.checked || slippageMode.value !== 'random' || normalSlippageFields.hidden;
            }
        };
        slippageToggle.addEventListener('change', updateSlippageFields);
        slippageMode.addEventListener('change', updateSlippageFields);
        document.getElementById('distribution').addEventListener('change', updateSlippageFields);
        updateSlippageFields();
        const partialFillMode = document.getElementById('partial_fill_mode');
        const fixedFillFields = document.getElementById('partial-fill-fixed-fields');
        const volumeFillFields = document.getElementById('partial-fill-volume-fields');
        const updatePartialFillFields = () => {
            partialFillMode.disabled = !partialFillToggle.checked;
            fixedFillFields.hidden = partialFillMode.value !== 'fixed';
            volumeFillFields.hidden = partialFillMode.value !== 'volume';
            for (const input of fixedFillFields.querySelectorAll('input, select')) {
                input.disabled = !partialFillToggle.checked || fixedFillFields.hidden;
            }
            for (const input of volumeFillFields.querySelectorAll('input, select')) {
                input.disabled = !partialFillToggle.checked || volumeFillFields.hidden;
            }
            document.getElementById('unfilled_order_policy').disabled = !partialFillToggle.checked;
        };
        partialFillToggle.addEventListener('change', updatePartialFillFields);
        partialFillMode.addEventListener('change', updatePartialFillFields);
        updatePartialFillFields();
        const tradeCostsToggle = document.getElementById('trade_costs_enabled');
        const tradeCostToggles = [...document.querySelectorAll('.trade-cost-toggle')];
        const updateTradeCostFields = () => {
            for (const toggle of tradeCostToggles) {
                const fields = document.getElementById(toggle.id.replace('_enabled', '_fields'));
                fields.hidden = !tradeCostsToggle.checked || !toggle.checked;
                for (const input of fields.querySelectorAll('input, select')) {
                    input.disabled = fields.hidden;
                }
                toggle.disabled = !tradeCostsToggle.checked;
            }
        };
        tradeCostsToggle.addEventListener('change', updateTradeCostFields);
        tradeCostToggles.forEach((toggle) => toggle.addEventListener('change', updateTradeCostFields));
        updateTradeCostFields();
    </script>
@endsection
