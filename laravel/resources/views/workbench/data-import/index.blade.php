@extends('workbench.layout')

@section('title', 'Data import')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">MARKET DATA / IMPORT</span>
            <h1>Data import</h1>
            <p>Pull candles from an external data provider or enable a short-lived API receiver.</p>
        </div>
    </div>

    <div class="data-import-grid">
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">EXTERNAL PROVIDER</span><h2>Pull from data provider</h2></div>
                <span class="record-count">ON DEMAND</span>
            </div>
            <form method="POST" action="{{ route('workbench.data-import.external-provider') }}" class="data-import-form">
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
                <label class="data-import-field data-import-full">
                    <span>Provider credential</span>
                    <input class="text-input" name="external_provider_credential" type="password" autocomplete="off" required>
                    <small>The credential is sent to the configured external provider for this request and is not saved by this application.</small>
                </label>
                <div class="data-import-full data-import-note">
                    Imports the external provider's available history for the selected pair and timeframe. If the provider returns general OHLC only, bid/ask columns remain null rather than being synthesized.
                </div>
                <button class="button button-primary data-import-full" type="submit">Pull provider data <span>→</span></button>
            </form>
        </section>

        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">PUSH API</span><h2>Receive data via API</h2></div>
                <span class="record-count">{{ $activeSessions->count() }} ACTIVE</span>
            </div>
            <div class="data-import-form">
                <p class="data-import-copy">Receiver is off by default. Start a session for one pair and timeframe to issue a bearer token; stop it as soon as sending is complete.</p>
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
                    <button class="button button-primary data-import-full" type="submit">Start receiving <span>→</span></button>
                </form>

                @if (session('data_import_token'))
                    <div class="data-import-token">
                        <strong>Copy this token now</strong>
                        <p>This token is shown only once and becomes invalid when the session stops.</p>
                        <code>{{ session('data_import_token') }}</code>
                        <p>POST <code>{{ url('/api/data-import/candles') }}</code> with an <code>Authorization: Bearer …</code> header and JSON candles as shown below.</p>
                    </div>
                @endif
                <div class="data-import-api-example">
                    <strong>Request body · OHLC and optional bid/ask OHLC</strong>
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
      "ask_close": 1.1042
    }
  ]
}</code></pre>
                    <small>Send up to 1,000 candles per request. Timestamps use <code>Y-m-d H:i:s</code>; repeated timestamps update existing candles. The bid/ask fields are optional: omit them if the feed does not provide bid and ask prices, and those columns will remain null.</small>
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
@endsection
