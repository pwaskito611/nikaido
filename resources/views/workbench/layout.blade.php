<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Workbench') · {{ config('app.name', 'Nikaido') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="workbench-body">
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="{{ route('workbench.index') }}">
                <span class="brand-mark">N</span>
                <span>Nikaido<span class="brand-caption">TRADING WORKBENCH</span></span>
            </a>
            <div class="nav-label">WORKSPACE</div>
            <nav class="sidebar-nav">
                <a href="{{ route('workbench.index') }}" class="{{ request()->routeIs('workbench.index') ? 'active' : '' }}"><span class="nav-icon">⌂</span> Overview</a>
                <a href="{{ route('workbench.market-types.index') }}" class="{{ request()->routeIs('workbench.market-types.*') ? 'active' : '' }}"><span class="nav-icon">◈</span> Market types</a>
                <a href="{{ route('workbench.market-pairs.index') }}" class="{{ request()->routeIs('workbench.market-pairs.*') ? 'active' : '' }}"><span class="nav-icon">⇄</span> Market pairs</a>
                <a href="{{ route('workbench.timeframes.index') }}" class="{{ request()->routeIs('workbench.timeframes.*') ? 'active' : '' }}"><span class="nav-icon">◷</span> Timeframes</a>
                <a href="{{ route('workbench.market-prices.index') }}" class="{{ request()->routeIs('workbench.market-prices.*') ? 'active' : '' }}"><span class="nav-icon">⌁</span> Market prices</a>
                <a href="{{ route('workbench.tests.create') }}" class="{{ request()->routeIs('workbench.tests.*') ? 'active' : '' }}"><span class="nav-icon">▷</span> Run a test</a>
            </nav>
            <div class="sidebar-footer"><span class="pulse-dot"></span> Engine ready</div>
        </aside>
        <main class="main-panel">
            <header class="topbar">
                <div><span class="eyebrow">BACKTESTING / WORKBENCH</span><span class="topbar-title">@yield('title', 'Overview')</span></div>
                <a class="topbar-link" href="{{ route('workbench.tests.create') }}">New backtest <span>↗</span></a>
            </header>
            <div class="page-content">
                @if (session('status'))
                    <div class="notice notice-success">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="notice notice-error">
                        <strong>Periksa kembali input:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
