@extends('workbench.layout')

@section('title', 'Overview')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">SYSTEMATIC TRADING / RESEARCH WORKBENCH</span>
            <h1>Research workspace</h1>
            <p>Configure market data, run reproducible historical tests, and inspect strategy outcomes.</p>
        </div>
        <a class="button button-primary" href="{{ route('workbench.tests.create') }}">Configure backtest <span>→</span></a>
    </div>

    <section class="data-panel workbench-overview-panel">
        <div class="panel-top">
            <div><span class="eyebrow">RESEARCH CYCLE</span><h2>From market data to evaluation</h2></div>
            <span class="record-count">CONFIGURE · EXECUTE · REVIEW</span>
        </div>
        <div class="overview-cycle">
            <div class="overview-cycle-step">
                <span class="overview-step-index">01</span>
                <div><strong>Prepare the universe</strong><p>Define market types, pairs, and candle intervals used by the engine.</p></div>
                <a href="{{ route('workbench.market-types.index') }}">Market types <span>↗</span></a>
            </div>
            <div class="overview-cycle-step">
                <span class="overview-step-index">02</span>
                <div><strong>Load and inspect prices</strong><p>Import provider data, receive pushed candles, or inspect historical OHLC.</p></div>
                <a href="{{ route('workbench.data-import.index') }}">Data import <span>↗</span></a>
            </div>
            <div class="overview-cycle-step">
                <span class="overview-step-index">03</span>
                <div><strong>Run and evaluate</strong><p>Test a specific pair or schedule eligible pairs within a market type.</p></div>
                <a href="{{ route('workbench.tests.create') }}">Backtest setup <span>↗</span></a>
            </div>
        </div>
    </section>

    <div class="overview-module-grid">
        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">MARKET DATA</span><h2>Data and universe</h2></div>
                <span class="record-count">INPUTS</span>
            </div>
            <div class="overview-link-list">
                <a href="{{ route('workbench.market-types.index') }}">
                    <span class="overview-link-code">01</span>
                    <span class="overview-link-copy"><strong>Market types</strong><small>Classify groups of market pairs.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.market-pairs.index') }}">
                    <span class="overview-link-code">02</span>
                    <span class="overview-link-copy"><strong>Market pairs</strong><small>Manage instruments and their market type.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.timeframes.index') }}">
                    <span class="overview-link-code">03</span>
                    <span class="overview-link-copy"><strong>Timeframes</strong><small>Maintain candle intervals used by tests and data.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.market-prices.index') }}">
                    <span class="overview-link-code">04</span>
                    <span class="overview-link-copy"><strong>Market prices</strong><small>Browse, filter, and maintain historical OHLC prices.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.data-import.index') }}">
                    <span class="overview-link-code">05</span>
                    <span class="overview-link-copy"><strong>Data import</strong><small>Pull from a configured provider or enable a scoped push session.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.market-exploration.index') }}">
                    <span class="overview-link-code">06</span>
                    <span class="overview-link-copy"><strong>Market exploration</strong><small>Inspect historical candles in a scrollable naked chart.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
            </div>
        </section>

        <section class="data-panel">
            <div class="panel-top">
                <div><span class="eyebrow">BACKTESTING</span><h2>Experiments and robustness</h2></div>
                <span class="record-count">ENGINE OUTPUT</span>
            </div>
            <div class="overview-link-list">
                <a href="{{ route('workbench.tests.create') }}">
                    <span class="overview-link-code">01</span>
                    <span class="overview-link-copy"><strong>Backtest configuration</strong><small>Run one pair or queue eligible pairs for a market type.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <div class="overview-capability">
                    <span class="overview-link-code">02</span>
                    <span class="overview-link-copy"><strong>Result analysis</strong><small>Review decisions, order history, capital movement, and cluster summaries.</small></span>
                </div>
                <div class="overview-capability">
                    <span class="overview-link-code">03</span>
                    <span class="overview-link-copy"><strong>Robustness evaluation</strong><small>Run fluctuation stress scenarios and Monte Carlo simulations on saved results.</small></span>
                </div>
            </div>
            <div class="overview-architecture">
                <span class="eyebrow">PROJECT BOUNDARIES</span>
                <p><strong>Strategy</strong> defines trading decisions. <strong>Engine</strong> loads price history, simulates orders, and produces backtest results. The Workbench configures research runs and presents their analysis.</p>
                <a href="{{ route('workbench.tests.create') }}">Start a research run <span>→</span></a>
            </div>
        </section>
    </div>

    <footer class="page-foot"><span>NIKAIDO · SYSTEMATIC TRADING RESEARCH</span><span>Historical data · Explicit configuration · Inspectable results</span></footer>
@endsection
