<x-app-layout :hide-fab="true">
    <a href="{{ route('lots.index') }}" class="detail-back">‹ 当落一覧へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">{{ $tour->name }}</div>
        <div class="vh-sub">このツアーへの申込と当落</div>
    </div>

    @if($tour->deadlines->isNotEmpty())
    <div class="sec-label">申込・締切</div>
    @foreach($tour->deadlines as $dl)
    <div class="d-block" style="margin-bottom:4px;padding:8px 12px;font-size:12px;">
        @if($dl->label)<strong>{{ $dl->label }}</strong> — @endif
        @if($dl->application_deadline)
        <span style="color:{{ $dl->isDeadlinePassed() ? 'var(--color-ink-sub)' : '#C7414F' }}">
            締切 {{ $dl->application_deadline->format('m.d') }}{{ $dl->isDeadlinePassed() ? '（締切済）' : '' }}
        </span>
        @endif
        @if($dl->announce_date)
        <span>・発表 {{ $dl->announce_date->format('m.d') }}</span>
        @endif
    </div>
    @endforeach
    @endif

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
