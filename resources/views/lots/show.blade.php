<x-app-layout :hide-fab="true">
    <a href="{{ route('lots.index') }}" class="detail-back">‹ 当落一覧へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">{{ $tour->name }}</div>
        <div class="vh-sub">このツアーへの申込と当落</div>
    </div>

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

    @if($pending->isEmpty() && $decided->isEmpty())
    <div class="empty-state" style="padding:24px;">このツアーへの申込はありません</div>
    @endif
</x-app-layout>
