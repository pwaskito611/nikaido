@extends('workbench.layout')

@section('title', 'Market exploration')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">PRICE ACTION / NAKED CHART</span>
            <h1>Market exploration</h1>
            <p>Explore historical OHLC candles without indicators.</p>
        </div>
    </div>

    <section class="data-panel results-panel exploration-panel">
        <div class="panel-top">
            <div><span class="eyebrow">CHART CONFIGURATION</span><h2>Choose market and date range</h2></div>
            <span class="record-count">OHLC · NO INDICATORS</span>
        </div>
        <form method="GET" action="{{ route('workbench.market-exploration.index') }}" class="exploration-filters" data-exploration-filters>
            <label class="exploration-field">
                <span>Search pair</span>
                <input class="text-input" type="search" placeholder="Search pair..." autocomplete="off" data-pair-search aria-controls="market-pair-select">
            </label>
            <label class="exploration-field">
                <span>Pair</span>
                <select class="text-input" id="market-pair-select" name="pair_id" required data-pair-select>
                    <option value="">Select pair</option>
                    @foreach ($marketPairs as $marketPair)
                        <option value="{{ $marketPair->id }}" @selected($selectedPairId === $marketPair->id)>
                            {{ $marketPair->base }}/{{ $marketPair->quote }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="exploration-field">
                <span>Timeframe</span>
                <select class="text-input" name="timeframe_type_id" required>
                    <option value="">Select timeframe</option>
                    @foreach ($timeframes as $timeframe)
                        <option value="{{ $timeframe->id }}" @selected($selectedTimeframeId === $timeframe->id)>{{ $timeframe->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="exploration-field">
                <span>From</span>
                <input class="text-input" name="from" type="text" data-flatpickr="date" value="{{ $from }}" required>
            </label>
            <label class="exploration-field">
                <span>To</span>
                <input class="text-input" name="to" type="text" data-flatpickr="date" value="{{ $to }}" required>
            </label>
            <button class="button button-primary exploration-submit" type="submit">Load chart <span>→</span></button>
        </form>
    </section>

    @if ($hasSearched)
        <section class="data-panel results-panel exploration-chart-panel">
            <div class="panel-top">
                <div>
                    <span class="eyebrow">NAKED CANDLESTICK CHART</span>
                    <h2>{{ $selectedPair?->base }}/{{ $selectedPair?->quote }} · {{ $selectedTimeframe?->name }}</h2>
                </div>
                <div class="exploration-chart-actions">
                    <span class="record-count">{{ number_format(count($candles)) }} CANDLES</span>
                    @if ($candles !== [])
                        <button class="button button-primary" type="button" data-run-strategy data-endpoint="{{ url('/api/market-exploration/run-strategy') }}">Run strategy <span>→</span></button>
                    @endif
                </div>
            </div>
            @if ($candles === [])
                <div class="empty-state"><strong>No candles found</strong><p>There is no OHLC data for this pair, timeframe, and date range.</p></div>
            @else
                <div class="exploration-strategy-summary metric-grid" data-strategy-summary hidden>
                    <div class="metric-card"><span class="eyebrow">TRADES</span><strong data-strategy-trades>0</strong></div>
                    <div class="metric-card"><span class="eyebrow">WINS</span><strong data-strategy-wins>0</strong></div>
                    <div class="metric-card"><span class="eyebrow">LOSSES</span><strong data-strategy-losses>0</strong></div>
                    <div class="metric-card"><span class="eyebrow">CLOSED TRADES</span><strong data-strategy-closed>0</strong></div>
                </div>
                <p class="exploration-strategy-status" data-strategy-status role="status" aria-live="polite"></p>
                <div class="chart-ohlc" data-chart-readout>Scroll over a candle to inspect OHLC values.</div>
                <div class="exploration-chart-scroll" data-chart-scroll>
                    <div class="exploration-chart-track" data-chart-track>
                        <canvas class="exploration-chart" data-market-chart data-candles="{{ json_encode($candles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}" aria-label="Scrollable naked candlestick chart"></canvas>
                    </div>
                </div>
                <div class="chart-marker-legend" aria-label="Chart marker legend">
                    <span class="chart-marker-legend-item"><span class="entry-marker-key">E ↑</span>LONG ENTRY</span>
                    <span class="chart-marker-legend-item"><span class="entry-marker-key">E ↓</span>SHORT ENTRY</span>
                    <span class="chart-marker-legend-item"><span class="exit-marker-key">C ↑</span>LONG CLOSE</span>
                    <span class="chart-marker-legend-item"><span class="exit-marker-key">C ↓</span>SHORT CLOSE</span>
                </div>
                <div class="chart-scroll-hint">Scroll horizontally or drag the chart to move through candle history. Price scale adjusts to the visible candles. Newest candles are shown first.</div>
            @endif
        </section>
    @endif
@endsection
