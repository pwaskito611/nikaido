@extends('workbench.layout')

@section('title', 'Overview')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow">RESEARCH CONSOLE · 01</span>
            <h1>Test an idea.<br><span>Read the market.</span></h1>
            <p>Kelola universe market Anda dan jalankan strategi pada data historis dari satu ruang kerja.</p>
            <a class="button button-primary" href="{{ route('workbench.tests.create') }}">Configure a backtest <span>→</span></a>
        </div>
        <div class="hero-graphic" aria-hidden="true">
            <div class="chart-label">MARKET SIGNAL <span>LIVE DATASET</span></div>
            <svg viewBox="0 0 440 210" role="img">
                <defs><linearGradient id="chartFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#a6e56f" stop-opacity=".28"/><stop offset="1" stop-color="#a6e56f" stop-opacity="0"/></linearGradient></defs>
                <path d="M0 166 L42 148 L73 158 L106 115 L143 130 L180 82 L216 99 L248 56 L286 71 L319 38 L355 57 L390 20 L440 34 L440 210 L0 210Z" fill="url(#chartFill)"/>
                <path d="M0 166 L42 148 L73 158 L106 115 L143 130 L180 82 L216 99 L248 56 L286 71 L319 38 L355 57 L390 20 L440 34" fill="none" stroke="#b5ed81" stroke-width="3"/>
                <path d="M0 190H440M0 140H440M0 90H440M0 40H440" stroke="#ffffff" stroke-opacity=".09" stroke-dasharray="3 7"/>
                <circle cx="390" cy="20" r="5" fill="#b5ed81"/>
            </svg>
            <div class="chart-axis"><span>HISTORICAL PRICE</span><span>+12.84%</span></div>
        </div>
        <div class="hero-index">01 — 03</div>
    </section>

    <section class="section-heading">
        <div><span class="eyebrow">GET STARTED</span><h2>Choose your next step</h2></div>
        <span class="section-note">Three tools. One workflow.</span>
    </section>
    <section class="dashboard-cards dashboard-cards-four">
        <a class="dashboard-card" href="{{ route('workbench.market-types.index') }}">
            <span class="card-index">01 / UNIVERSE</span><span class="card-symbol">◈</span>
            <h3>Market types</h3><p>Kelompokkan instrumen berdasarkan kelas aset atau pasar.</p>
            <span class="card-link">Manage market types <b>↗</b></span>
        </a>
        <a class="dashboard-card" href="{{ route('workbench.market-pairs.index') }}">
            <span class="card-index">02 / INSTRUMENTS</span><span class="card-symbol">⇄</span>
            <h3>Market pairs</h3><p>Atur pair yang tersedia untuk proses backtest.</p>
            <span class="card-link">Manage market pairs <b>↗</b></span>
        </a>
        <a class="dashboard-card" href="{{ route('workbench.timeframes.index') }}">
            <span class="card-index">03 / DATA INTERVALS</span><span class="card-symbol">◷</span>
            <h3>Timeframes</h3><p>Atur interval candle yang tersedia untuk pengujian.</p>
            <span class="card-link">Manage timeframes <b>↗</b></span>
        </a>
        <a class="dashboard-card card-featured" href="{{ route('workbench.tests.create') }}">
            <span class="card-index">04 / RESEARCH</span><span class="card-symbol">▷</span>
            <h3>Run a test</h3><p>Pilih pair, periode, dan jadwal strategi untuk memulai.</p>
            <span class="card-link">Open test setup <b>↗</b></span>
        </a>
    </section>
    <footer class="page-foot"><span>NIKAIDO WORKBENCH</span><span>Research with discipline.</span></footer>
@endsection
