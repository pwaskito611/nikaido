@extends('workbench.layout')

@section('title', 'Overview')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">INTRADAY & SWING · CFD & SPOT RESEARCH</span>
            <h1>Research workspace</h1>
            <p>Research intraday and swing ideas on your historical CFD or spot price data. Configure backtests and review results alongside their assumptions; this is a research tool, not live execution.</p>
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
                <div><strong>Prepare the universe and research data</strong><p>Define pairs and timeframes, then link company or economic fundamentals and asset cash-flow events.</p></div>
                <a href="{{ route('workbench.market-types.index') }}">Market types <span>↗</span></a>
            </div>
            <div class="overview-cycle-step">
                <span class="overview-step-index">02</span>
                <div><strong>Feed and explore historical data</strong><p>Enter records manually or receive pushed data from your sender, then inspect candles and run a strategy on an explored range.</p></div>
                <a href="{{ route('workbench.data-import.index') }}">Data import <span>↗</span></a>
            </div>
            <div class="overview-cycle-step">
                <span class="overview-step-index">03</span>
                <div><strong>Configure and evaluate a backtest</strong><p>Run a pair or market type, choose portfolio-target leverage controls, and review performance and robustness outputs.</p></div>
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
                <a href="{{ route('workbench.fundamentals.index') }}">
                    <span class="overview-link-code">05</span>
                    <span class="overview-link-copy"><strong>Fundamentals</strong><small>Manage company or economic entities and dated observations linked to pairs.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.cash-flows.index') }}">
                    <span class="overview-link-code">06</span>
                    <span class="overview-link-copy"><strong>CFD contract adjustments</strong><small>Record dividend adjustments in USD per underlying unit.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.data-import.index') }}">
                    <span class="overview-link-code">07</span>
                    <span class="overview-link-copy"><strong>Data import</strong><small>Start scoped push receivers for candles, fundamentals, and CFD adjustments; Nikaido does not fetch providers.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <a href="{{ route('workbench.market-exploration.index') }}">
                    <span class="overview-link-code">08</span>
                    <span class="overview-link-copy"><strong>Market exploration</strong><small>Search pairs, inspect a candle chart, and run the strategy with visible entry and exit markers.</small></span>
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
                    <span class="overview-link-copy"><strong>Backtest configuration</strong><small>Run one pair or queue eligible pairs; configure schedules, execution assumptions, costs, and optional cash-flow accounting.</small></span>
                    <span class="overview-link-arrow">↗</span>
                </a>
                <div class="overview-capability">
                    <span class="overview-link-code">02</span>
                    <span class="overview-link-copy"><strong>Capital management</strong><small>Choose shared risk capital allocated across pair entries, or separate capital for independent pair runs.</small></span>
                </div>
                <div class="overview-capability">
                    <span class="overview-link-code">03</span>
                    <span class="overview-link-copy"><strong>Results and robustness</strong><small>Review trade and capital metrics, cluster summaries, fluctuation stress scenarios, and Monte Carlo simulations.</small></span>
                </div>
            </div>
        </section>
    </div>

    <footer class="page-foot"><span>NIKAIDO · CFD & SPOT RESEARCH WORKBENCH</span><span>Intraday & swing · Historical data · Inspectable assumptions</span></footer>
@endsection
