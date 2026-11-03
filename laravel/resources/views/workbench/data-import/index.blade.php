@extends('workbench.layout')

@section('title', 'Data import')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">MARKET DATA / IMPORT</span>
            <h1>Data import</h1>
            <p>Choose the matching receiver for your data: price candles (OHLCV) or dated Fundamental observations. Their tokens and API endpoints are separate.</p>
        </div>
    </div>

    <div class="data-import-grid">
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">PRICE DATA · OHLCV</span><h2>Import market candles</h2></div>
                <span class="record-count">{{ $activeSessions->count() }} ACTIVE</span>
            </div>
            <div class="data-import-form">
                <p class="data-import-copy">For market prices only: open/high/low/close, optional bid/ask, and volume. This receiver is scoped to one market pair and timeframe.</p>
                <form method="POST" action="{{ route('workbench.data-import.incoming-sessions.start') }}" class="data-import-form-inner">
                    @csrf
                    <label class="data-import-field">
                        <span>Market pair</span>
                        <select class="text-input" name="pair_id" required>
                            <option value="">Select pair</option>
                            @foreach ($marketPairs as $marketPair)
                                <option value="{{ $marketPair->id }}" @selected((string) old('pair_id') === (string) $marketPair->id)>
                                    {{ $marketPair->base }}/{{ $marketPair->quote }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="data-import-field">
                        <span>Timeframe</span>
                        <select class="text-input" name="timeframe_type_id" required>
                            <option value="">Select timeframe</option>
                            @foreach ($timeframes as $timeframe)
                                <option value="{{ $timeframe->id }}" @selected((string) old('timeframe_type_id') === (string) $timeframe->id)>
                                    {{ $timeframe->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button button-primary data-import-full" type="submit">Start candle receiver <span>→</span></button>
                </form>

                @if (session('data_import_token'))
                    <div class="data-import-token">
                        <strong>Copy this token now</strong>
                        <p>OHLCV token: shown only once and invalidated when its pair/timeframe session stops.</p>
                        <code>{{ session('data_import_token') }}</code>
                        <p>POST <code>{{ url('/api/data-import/candles') }}</code> with an <code>Authorization: Bearer …</code> header and JSON candles as shown below.</p>
                    </div>
                @endif
                <p class="data-import-endpoint">CANDLE ENDPOINT · <code>POST {{ url('/api/data-import/candles') }}</code></p>
                <div class="data-import-api-example">
                    <strong>Request body · OHLC, optional bid/ask, and explicitly typed volume</strong>
                    <pre><code>{
  "candles": [
    {
      "time": "2026-10-12 10:00:00",
      "open": 1.1025,
      "high": 1.1050,
      "low": 1.1010,
      "close": 1.1040,
      "bid_open": 1.1024,
      "bid_high": 1.1048,
      "bid_low": 1.1009,
      "bid_close": 1.1038,
      "ask_open": 1.1026,
      "ask_high": 1.1052,
      "ask_low": 1.1011,
      "ask_close": 1.1042,
      "volume": 1250.5,
      "volume_type": "traded"
    }
  ]
}</code></pre>
                    <small>Send up to 1,000 candles per request. Timestamps use <code>Y-m-d H:i:s</code>; repeated timestamps update existing candles. Bid/ask fields are optional. Volume must use the pair's execution quantity units and include <code>volume_type</code> as <code>traded</code> or <code>tick</code>. Tick volume is not traded liquidity; the volume-based fill mode uses only candles marked <code>traded</code>.</small>
                </div>
            </div>
        </section>
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">FUNDAMENTAL DATA · INDICATORS</span><h2>Import economic/company observations</h2></div>
                <span class="record-count">{{ count($activeFundamentalSessions) }} ACTIVE</span>
            </div>
            <div class="data-import-form fundamental-import-card">
                <p class="data-import-copy">For dated indicators such as CPI, GDP, interest rates, revenue, or EPS. This receiver is scoped to one Fundamental entity—not a market pair or timeframe.</p>
                <form method="POST" action="{{ route('workbench.data-import.fundamental-sessions.start') }}" class="data-import-form-inner">
                    @csrf
                    <label class="data-import-field">
                        <span>Fundamental entity</span>
                        <select class="text-input" name="fundamental_id" required>
                            <option value="">Select entity</option>
                            @foreach ($fundamentals as $fundamentalEntity)
                                @php($hasActiveFundamentalSession = collect($activeFundamentalSessions)->contains('fundamental_id', $fundamentalEntity->id))
                                <option value="{{ $fundamentalEntity->id }}" @selected((string) old('fundamental_id') === (string) $fundamentalEntity->id) @disabled($hasActiveFundamentalSession)>
                                    {{ $fundamentalEntity->name }}{{ $fundamentalEntity->category ? ' · '.$fundamentalEntity->category : '' }}{{ $hasActiveFundamentalSession ? ' · RECEIVER ACTIVE' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($fundamentals->isEmpty())
                            <small>Create a Fundamental entity before starting an observations receiver.</small>
                        @endif
                    </label>
                    <button class="button button-primary data-import-full" type="submit" @disabled($fundamentals->isEmpty())>Start observations receiver <span>→</span></button>
                </form>

                @if (session('fundamental_import_token'))
                    <div class="data-import-token fundamental-import-token">
                        <strong>FUNDAMENTAL TOKEN · copy now</strong>
                        <p>Only for indicator observations. This token is shown once and invalidated when its session stops.</p>
                        <code>{{ session('fundamental_import_token') }}</code>
                        <p>POST <code>{{ url('/api/data-import/fundamentals/observations') }}</code> with a JSON <code>observations</code> array. Do not send candles to this endpoint.</p>
                    </div>
                @endif
                <p class="data-import-endpoint fundamental-import-endpoint">OBSERVATION ENDPOINT · <code>POST {{ url('/api/data-import/fundamentals/observations') }}</code></p>
                <div class="data-import-api-example fundamental-api-example">
                    <strong>Request body · dated observations, not OHLCV candles</strong>
                    <pre><code>{
  "observations": [
    {
      "code": "CPI",
      "name": "Consumer Price Index",
      "datetime": "2024-09-01 00:00:00",
      "released_at": "2024-10-10 08:30:00",
      "value": 1.1
    }
  ]
}</code></pre>
                    <small><code>datetime</code> is the observation period; <code>released_at</code> is when the market can know it. Values can be JSON numbers, strings, arrays, objects, booleans, or null. No pair/timeframe is selected here. The entity's linked pairs determine which backtests receive this data.</small>
                </div>
            </div>
        </section>
    </div>

    <section class="data-panel data-import-sessions">
        <div class="panel-top">
            <div><span class="eyebrow">RECEIVER STATUS</span><h2>Incoming sessions</h2></div>
            <span class="record-count">OFF WHEN NO SESSION IS ACTIVE</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>PAIR</th><th>TIMEFRAME</th><th>STATUS</th><th>STARTED</th><th>COMPLETED</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentSessions as $importSession)
                        <tr>
                            <td>{{ $importSession->pair->base }}/{{ $importSession->pair->quote }}</td>
                            <td>{{ $importSession->timeframeType->name }}</td>
                            <td><span class="data-import-status {{ $importSession->status === 'active' ? 'is-active' : '' }}">{{ strtoupper($importSession->status) }}</span></td>
                            <td>{{ $importSession->started_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $importSession->stopped_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td>
                                @if ($importSession->status === 'active')
                                    <form method="POST" action="{{ route('workbench.data-import.incoming-sessions.stop', $importSession->id) }}">
                                        @csrf
                                        <button class="button button-secondary data-import-stop" type="submit">Stop</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-table">No incoming sessions. The API receiver is currently off.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="data-panel data-import-sessions fundamental-import-sessions">
        <div class="panel-top">
            <div><span class="eyebrow">FUNDAMENTAL RECEIVER STATUS</span><h2>Observation import sessions</h2></div>
            <span class="record-count">SEPARATE FROM CANDLE RECEIVERS</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>FUNDAMENTAL ENTITY</th><th>STATUS</th><th>STARTED</th><th>STOPPED</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentFundamentalSessions as $importSession)
                        <tr>
                            <td>{{ $importSession->fundamental->name }}</td>
                            <td><span class="data-import-status {{ $importSession->status === 'active' ? 'is-active' : '' }}">{{ strtoupper($importSession->status) }}</span></td>
                            <td>{{ $importSession->started_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $importSession->stopped_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td>
                                @if ($importSession->status === 'active')
                                    <form method="POST" action="{{ route('workbench.data-import.fundamental-sessions.stop', [$importSession->fundamental_id, $importSession->id]) }}">
                                        @csrf
                                        <button class="button button-secondary data-import-stop" type="submit">Stop observations receiver</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No Fundamental observation import sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
