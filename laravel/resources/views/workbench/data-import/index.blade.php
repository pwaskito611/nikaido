@extends('workbench.layout')

@section('title', 'Data import')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">MARKET DATA / IMPORT</span>
            <h1>Data import</h1>
            <p>Choose the matching receiver for candles, Fundamental observations, news articles, or CFD dividend adjustments. Their tokens and API endpoints are separate.</p>
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
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">MARKET CONTEXT · NEWS</span><h2>Import news articles</h2></div>
                <span class="record-count">{{ count($activeNewsSessions) }} ACTIVE</span>
            </div>
            <div class="data-import-form">
                <p class="data-import-copy">News receivers are scoped to one pair. Provide publication time and the first time the information was actually available; historical backtests hide news until <code>available_at</code>.</p>
                <form method="POST" action="{{ route('workbench.data-import.news-sessions.start') }}" class="data-import-form-inner">
                    @csrf
                    <label class="data-import-field">
                        <span>Market pair</span>
                        <select class="text-input" name="market_pair_id" required>
                            <option value="">Select pair</option>
                            @foreach ($marketPairs as $marketPair)
                                @php($hasActiveNewsSession = collect($activeNewsSessions)->contains('market_pair_id', $marketPair->id))
                                <option value="{{ $marketPair->id }}" @selected((string) old('market_pair_id') === (string) $marketPair->id) @disabled($hasActiveNewsSession)>
                                    {{ $marketPair->base }}/{{ $marketPair->quote }}{{ $hasActiveNewsSession ? ' · RECEIVER ACTIVE' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button class="button button-primary data-import-full" type="submit" @disabled($marketPairs->isEmpty())>Start news receiver <span>→</span></button>
                </form>

                @if (session('news_import_token'))
                    <div class="data-import-token">
                        <strong>NEWS TOKEN · copy now</strong>
                        <p>This pair-scoped bearer token is shown once and invalidated when its session stops.</p>
                        <code>{{ session('news_import_token') }}</code>
                        <p>POST <code>{{ url('/api/data-import/news/articles') }}</code> with a JSON <code>articles</code> array.</p>
                    </div>
                @endif
                <p class="data-import-endpoint">NEWS ENDPOINT · <code>POST {{ url('/api/data-import/news/articles') }}</code></p>
                <div class="data-import-api-example">
                    <strong>Request body · news and pair-specific impact annotations</strong>
                    <pre><code>{
  "articles": [
    {
      "source": "provider-name",
      "external_id": "NEWS-123",
      "title": "Central bank rate decision",
      "content": "Article text...",
      "published_at": "2026-10-12 10:00:00",
      "available_at": "2026-10-12 10:00:00",
      "language": "en",
      "relevance_score": 0.9,
      "impact_score": -0.6,
      "impact_lag_seconds": 0,
      "impact_duration_seconds": 7200,
      "confidence_score": 0.8,
      "assessment_method": "provider"
    }
  ]
}</code></pre>
                    <small>Send 1–1,000 articles (maximum 5 MB). Score ranges: relevance/confidence 0–1, impact -1–1. Impact is directional for the session's pair. A stable source and external ID make repeated imports idempotent. Date-times use <code>Y-m-d H:i:s</code>.</small>
                </div>
            </div>
        </section>
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">CFD CONTRACT ADJUSTMENT</span><h2>Import dividend adjustments</h2></div>
                <span class="record-count">{{ count($activeCashFlowSessions) }} ACTIVE</span>
            </div>
            <div class="data-import-form">
                <p class="data-import-copy">For dividend/contract adjustments on CFD positions. Amounts are USD per underlying unit and are bound to one market pair.</p>
                <form method="POST" action="{{ route('workbench.data-import.cash-flow-sessions.start') }}" class="data-import-form-inner">
                    @csrf
                    <label class="data-import-field">
                        <span>Market pair</span>
                        <select class="text-input" name="market_pair_id" required>
                            <option value="">Select pair</option>
                            @foreach ($marketPairs as $marketPair)
                                @php($hasActiveCashFlowSession = collect($activeCashFlowSessions)->contains('market_pair_id', $marketPair->id))
                                <option value="{{ $marketPair->id }}" @selected((string) old('market_pair_id') === (string) $marketPair->id) @disabled($hasActiveCashFlowSession)>
                                    {{ $marketPair->base }}/{{ $marketPair->quote }}{{ $hasActiveCashFlowSession ? ' · RECEIVER ACTIVE' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($marketPairs->isEmpty())
                            <small>Create a market pair before starting an adjustment receiver.</small>
                        @endif
                    </label>
                    <button class="button button-primary data-import-full" type="submit" @disabled($marketPairs->isEmpty())>Start adjustment receiver <span>→</span></button>
                </form>

                @if (session('cash_flow_import_token'))
                    <div class="data-import-token">
                        <strong>CONTRACT ADJUSTMENT TOKEN · copy now</strong>
                        <p>Only for CFD dividend adjustments. This token is shown once and invalidated when its pair session stops.</p>
                        <code>{{ session('cash_flow_import_token') }}</code>
                        <p>POST <code>{{ url('/api/data-import/cash-flows/events') }}</code> with a JSON <code>events</code> array.</p>
                    </div>
                @endif
                <p class="data-import-endpoint">CASH FLOW ENDPOINT · <code>POST {{ url('/api/data-import/cash-flows/events') }}</code></p>
                <div class="data-import-api-example">
                    <strong>Request body · dated CFD contract adjustments</strong>
                    <pre><code>{
  "events": [
    {
      "event_type": "dividend_adjustment",
      "amount_per_unit": 0.50,
      "announced_at": "2026-03-01 09:00:00",
      "available_at": "2026-03-01 09:00:00",
      "ex_date": "2026-03-20 00:00:00",
      "record_date": "2026-03-21 00:00:00",
      "payable_at": "2026-04-05 00:00:00",
      "status": "confirmed",
      "source": "provider",
      "external_id": "DIV-2026-001"
    }
  ]
}</code></pre>
                    <small>Send 1–1,000 adjustments. Set <code>event_type</code> to <code>dividend_adjustment</code>; <code>available_at</code> and positive <code>amount_per_unit</code> in USD per underlying unit are required. Dates use <code>Y-m-d H:i:s</code>; optional dates may be omitted when unknown. A stable external ID makes repeated feed updates idempotent.</small>
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

    <section class="data-panel data-import-sessions">
        <div class="panel-top">
            <div><span class="eyebrow">NEWS RECEIVER STATUS</span><h2>News import sessions</h2></div>
            <span class="record-count">PAIR-SCOPED</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>PAIR</th><th>STATUS</th><th>STARTED</th><th>STOPPED</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentNewsSessions as $importSession)
                        <tr>
                            <td>{{ $importSession->marketPair->base }}/{{ $importSession->marketPair->quote }}</td>
                            <td><span class="data-import-status {{ $importSession->status === 'active' ? 'is-active' : '' }}">{{ strtoupper($importSession->status) }}</span></td>
                            <td>{{ $importSession->started_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $importSession->stopped_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td>
                                @if ($importSession->status === 'active')
                                    <form method="POST" action="{{ route('workbench.data-import.news-sessions.stop', $importSession->id) }}">
                                        @csrf
                                        <button class="button button-secondary data-import-stop" type="submit">Stop news receiver</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No news import sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="data-panel data-import-sessions">
        <div class="panel-top">
            <div><span class="eyebrow">CASH FLOW RECEIVER STATUS</span><h2>Cash flow import sessions</h2></div>
            <span class="record-count">SEPARATE FROM CANDLE AND FUNDAMENTAL RECEIVERS</span>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>PAIR</th><th>STATUS</th><th>STARTED</th><th>STOPPED</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentCashFlowSessions as $importSession)
                        <tr>
                            <td>{{ $importSession->marketPair->base }}/{{ $importSession->marketPair->quote }}</td>
                            <td><span class="data-import-status {{ $importSession->status === 'active' ? 'is-active' : '' }}">{{ strtoupper($importSession->status) }}</span></td>
                            <td>{{ $importSession->started_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $importSession->stopped_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td>
                                @if ($importSession->status === 'active')
                                    <form method="POST" action="{{ route('workbench.data-import.cash-flow-sessions.stop', [$importSession->market_pair_id, $importSession->id]) }}">
                                        @csrf
                                        <button class="button button-secondary data-import-stop" type="submit">Stop cash flow receiver</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No cash flow import sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
