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


        </div>
        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;">
            <a href="{{ route('events.edit', $event) }}" class="sched-tag" style="text-decoration:none;font-size:11px;">編集</a>
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

    <div class="sec-label">この公演の記録</div>
    <a href="{{ route('setlists.show', $tour) }}" class="link-row" style="display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:inherit;">
        <span>🎵 セットリスト</span><span class="lr-arrow">›</span>
    </a>

    {{-- ＋日程を追加（mockup: ツアー詳細側の m-add） --}}
    <a href="{{ route('events.create', $tour) }}" class="m-add">＋ 日程を追加</a>

    {{-- 申込締切・当落発表日（tour_deadlines） --}}
    <div class="sec-label">申込締切・当落発表日</div>
    @foreach($tour->deadlines as $dl)
    <div class="d-block" style="margin-bottom:6px;padding:8px 12px;">
        <form method="POST" action="{{ route('tours.update-deadline', [$tour, $dl]) }}">
            @csrf @method('PUT')
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                <input class="f-input" type="text" name="label" value="{{ $dl->label }}" placeholder="ラベル" style="flex:1;font-size:12px;">
            </div>
            <div style="display:flex;gap:6px;">
                <div style="flex:1;">
                    <label style="font-size:10px;color:var(--color-ink-sub);">締切</label>
                    <input class="f-input" type="date" name="application_deadline"
                           value="{{ optional($dl->application_deadline)->format('Y-m-d') }}" style="font-size:11px;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:10px;color:var(--color-ink-sub);">発表</label>
                    <input class="f-input" type="date" name="announce_date"
                           value="{{ optional($dl->announce_date)->format('Y-m-d') }}" style="font-size:11px;">
                </div>
                <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 8px;align-self:flex-end;">保存</button>
            </div>
        </form>
        <form method="POST" action="{{ route('tours.destroy-deadline', [$tour, $dl]) }}" style="margin-top:4px;text-align:right;"
              onsubmit="return confirm('この締切を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 6px;color:#C7414F;">削除</button>
        </form>
    </div>
    @endforeach

    <form method="POST" action="{{ route('tours.update-deadlines', $tour) }}">
        @csrf
        <div class="d-block" style="padding:10px 12px;">
            <div class="d-h">締切を追加</div>
            <div class="f-field" style="margin-bottom:6px;">
                <input class="f-input" type="text" name="label" placeholder="ラベル（例：FC先行・一般先行）" style="font-size:12px;">
            </div>
            <div style="display:flex;gap:8px;">
                <div style="flex:1;">
                    <label style="font-size:10px;color:var(--color-ink-sub);">申込締切</label>
                    <input class="f-input" type="date" name="application_deadline" style="font-size:12px;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:10px;color:var(--color-ink-sub);">発表日</label>
                    <input class="f-input" type="date" name="announce_date" style="font-size:12px;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px;">追加</button>
        </div>
    </form>

    @if($events->isEmpty())
    <form method="POST" action="{{ route('tours.destroy', $tour) }}" style="margin-top:14px;"
          onsubmit="return confirm('このツアーを削除しますか？')">
        @csrf @method('DELETE')
        <button type="submit" class="f-danger">このツアーを削除する</button>
    </form>
    @endif
</x-app-layout>
