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
        <a href="{{ route('identities.index') }}" class="{{ request()->routeIs('identities.*') ? 'on' : '' }}">
            <span class="bar"></span>名義
        </a>
        <a href="{{ route('lots.index') }}" class="{{ request()->routeIs('lots.*') ? 'on' : '' }}">
            <span class="bar"></span>当落
        </a>
        <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'on' : '' }}">
            <span class="bar"></span>公演
        </a>
    </nav>
    @endunless
</div>
@unless($hideNav)
<script nonce="{{ $cspNonce ?? '' }}">
(function(){
    var tabs = [
        '{{ route('home') }}',
        '{{ route('attendances.index') }}',
        '{{ route('identities.index') }}',
        '{{ route('lots.index') }}',
        '{{ route('events.index') }}'
    ];
    var current = tabs.findIndex(function(u){ return window.location.pathname === new URL(u).pathname; });
    if(current < 0) return;

    var sx=0, sy=0, swiping=false;
    document.addEventListener('touchstart', function(e){
        sx = e.touches[0].clientX; sy = e.touches[0].clientY; swiping = true;
    }, {passive:true});
    document.addEventListener('touchmove', function(e){
        if(!swiping) return;
        var dx = e.touches[0].clientX - sx;
        var dy = e.touches[0].clientY - sy;
        if(Math.abs(dy) > Math.abs(dx)){ swiping = false; }
    }, {passive:true});
    document.addEventListener('touchend', function(e){
        if(!swiping) return;
        swiping = false;
        var dx = e.changedTouches[0].clientX - sx;
        if(Math.abs(dx) < 60) return;
        var next = dx < 0 ? current + 1 : current - 1;
        if(next >= 0 && next < tabs.length){
            window.location.href = tabs[next];
        }
    }, {passive:true});
})();
</script>
@endunless
@stack('scripts')
</body>
</html>
