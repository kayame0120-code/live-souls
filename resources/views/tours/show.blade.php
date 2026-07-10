<x-app-layout :hide-fab="true">
    <a href="{{ route('events.index') }}" class="detail-back">‹ 公演一覧へ戻る</a>

    @php
        $today = \Illuminate\Support\Carbon::today();
        $hasUpcoming = $events->contains(fn ($e) => $e->event_date->gte($today));
        $status = $events->isEmpty() ? '日程未登録' : ($hasUpcoming ? '開催中' : '終了');
    @endphp
    <div class="venue-hero">
        <div class="vh-name">{{ $tour->name }}</div>
        <div class="vh-sub">{{ $status }} ・ 全{{ $events->count() }}公演</div>
    </div>

    <div class="sec-label">日程</div>
    @forelse($events as $event)
    <div class="sched-row">
        <div class="sched-date">{{ $event->event_date->format('m.d') }}<br>（{{ $event->event_date->translatedFormat('D') }}）</div>
        <div class="sched-body">
            <div class="sched-venue">
                {{ $event->venue?->name ?? '会場未設定' }}@if($event->event_label) {{ $event->event_label }}@endif
            </div>
            @if($event->start_time)
            <div class="sched-time">開演 {{ $event->start_time->format('H:i') }}</div>
            @endif
            @if($event->application_deadline)
            <div class="sched-time" style="color:{{ $event->isDeadlinePassed() ? 'var(--color-ink-sub)' : '#C7414F' }}">
                締切 {{ $event->application_deadline->format('m.d H:i') }}{{ $event->isDeadlinePassed() ? '（締切済）' : '' }}
            </div>
            @endif
            @if($event->announce_date)
            <div class="sched-time">発表 {{ $event->announce_date->format('m.d') }}</div>
            @endif
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;">
            @if($event->setlist)
            <a href="{{ route('setlists.show', $event) }}" class="sched-tag" style="text-decoration:none;">セトリ</a>
            @endif
            @if($event->canBeDeleted())
            <form method="POST" action="{{ route('events.destroy', $event) }}"
                  onsubmit="return confirm('この日程を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="sched-del">削除</button>
            </form>
            @else
            <span class="sched-tag {{ $event->event_date->lt($today) ? 'past' : '' }}">{{ $event->event_date->lt($today) ? '終了' : '予定' }}</span>
            @endif
        </div>
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">まだ日程がありません</div>
    @endforelse

    {{-- ＋日程を追加（mockup: ツアー詳細側の m-add） --}}
    <a href="{{ route('events.create', $tour) }}" class="m-add">＋ 日程を追加</a>

    @if($events->isEmpty())
    <form method="POST" action="{{ route('tours.destroy', $tour) }}" style="margin-top:14px;"
          onsubmit="return confirm('このツアーを削除しますか？')">
        @csrf @method('DELETE')
        <button type="submit" class="f-danger">このツアーを削除する</button>
    </form>
    @endif
</x-app-layout>
