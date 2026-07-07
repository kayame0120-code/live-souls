@props(['hideHeader' => false, 'hideFab' => false, 'hideNav' => false, 'pageHeader' => null, 'title' => '現場手帖'])

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="phone">
    @unless($hideHeader)
    <header class="app-header">
        <div class="app-name">現場手帖<small>GENBA TECHO</small></div>
        <div class="header-date">{{ now()->translatedFormat('Y.m.d（D）') }}</div>
    </header>
    @endunless

    @if($pageHeader)
    {{ $pageHeader }}
    @endif

    <main class="app-main">
        <div class="screen-content">
            @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </div>
    </main>

    @unless($hideFab)
    <a href="{{ route('attendances.create') }}" class="fab">＋ 参戦を記録</a>
    @endunless

    @unless($hideNav)
    <nav class="bottom-nav" aria-label="メインナビゲーション">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'on' : '' }}">
            <span class="bar"></span>ホーム
        </a>
        <a href="{{ route('attendances.index') }}" class="{{ request()->routeIs('attendances.*') ? 'on' : '' }}">
            <span class="bar"></span>参戦記録
        </a>
        <a href="{{ route('identities.index') }}" class="{{ request()->routeIs('identities.*') || request()->routeIs('identity-groups.*') ? 'on' : '' }}">
            <span class="bar"></span>名義
        </a>
        <a href="{{ route('lots.index') }}" class="{{ request()->routeIs('lots.*') ? 'on' : '' }}">
            <span class="bar"></span>当落
        </a>
    </nav>
    @endunless
</div>
@stack('scripts')
</body>
</html>
