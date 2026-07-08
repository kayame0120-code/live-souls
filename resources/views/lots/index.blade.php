<x-app-layout>
    {{-- 申込登録・公演一覧への導線（v1.2: 一括インポートは公演側へ移設） --}}
    <div class="link-row">
        <a href="{{ route('lots.create') }}" class="primary">＋ 申込を登録</a>
        <a href="{{ route('events.index') }}">公演一覧</a>
    </div>

    @if($pending->isEmpty() && $decided->isEmpty())
        <div class="empty-state">
            当落待ちの申込はありません<br>
            <a href="{{ route('lots.create') }}" class="btn btn-secondary btn-sm" style="margin-top:12px;">申込を登録する</a>
        </div>
    @else
        @if($pending->isNotEmpty())
        <div class="sec-label">当落待ち</div>
        @foreach($pending as $attendance)
        @include('lots._lot-card', ['attendance' => $attendance])
        @endforeach
        @endif

        @if($decided->isNotEmpty())
        <div class="sec-label">結果</div>
        @foreach($decided as $attendance)
        @include('lots._lot-card', ['attendance' => $attendance])
        @endforeach
        @endif
    @endif
</x-app-layout>
