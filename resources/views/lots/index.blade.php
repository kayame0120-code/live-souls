<x-app-layout>
    {{-- 申込登録・一括インポートへの導線（spec v1.1 §3） --}}
    <div style="display:flex; gap:8px; margin-bottom:16px;">
        <a href="{{ route('lots.create') }}" class="btn btn-primary" style="flex:1; width:auto;">＋ 申込を登録</a>
        <a href="{{ route('lots.import') }}" class="btn btn-secondary" style="flex:1;">一括インポート</a>
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
