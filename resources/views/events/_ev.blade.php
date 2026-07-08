{{-- 公演カード（mockup v1.3 .ev）: 日付+曜 / 公演名 / 会場〈開演〉 / 参戦N or 削除 --}}
<div class="ev">
    <div class="ev-date">{{ $event->event_date->format('m.d') }}<br>（{{ $event->event_date->translatedFormat('D') }}）</div>
    <div class="ev-body">
        <div class="ev-name">{{ $event->event_name }}</div>
        <div class="ev-venue">
            {{ $event->venue?->name ?? '会場未設定' }}
            @if($event->start_time)<span class="ev-time">開演 {{ $event->start_time->format('H:i') }}</span>@endif
        </div>
    </div>
    @if($event->attendances_count > 0)
        <span class="ev-count">参戦 {{ $event->attendances_count }}</span>
    @else
        {{-- 参戦0件のみ削除可（既存挙動を維持） --}}
        <form method="POST" action="{{ route('events.destroy', $event) }}"
              onsubmit="return confirm('この公演を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="ev-delete">削除</button>
        </form>
    @endif
</div>
