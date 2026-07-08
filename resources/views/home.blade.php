<x-app-layout>
    <div class="sec-label">次の現場</div>
    @if($nextAttendance && $nextAttendance->event_date)
        @php
            $days = now()->startOfDay()->diffInDays($nextAttendance->event_date, false);
            $oshiColor = optional($nextAttendance->fcMemberships->first())->oshi_color ?? '#C7414F';
        @endphp
        <div class="next-card" style="--oshi-color: {{ $oshiColor }}">
            <div class="countdown">
                <span class="num">{{ $days }}</span><span class="unit">日</span>
                <span class="label">{{ $nextAttendance->event_date->format('m.d') }}（{{ $nextAttendance->event_date->translatedFormat('D') }}）</span>
            </div>
            <div class="next-title">{{ $nextAttendance->event_name }}</div>
            <div class="next-meta">
                @if($nextAttendance->venue)
                <span>会場 <b>{{ $nextAttendance->venue->name }}</b></span>
                @endif
                {{-- ★v1.3（QV13-1）：開演は公演(event)の属性を参照 --}}
                @if($nextAttendance->event?->start_time)
                <span>開演 <b>{{ $nextAttendance->event->start_time->format('H:i') }}</b></span>
                @endif
            </div>
            @if($nextAttendance->fcMemberships->isNotEmpty())
                @php $m = $nextAttendance->fcMemberships->first(); @endphp
                <span class="meigi-chip">
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}で入場
                </span>
            @endif
        </div>
    @else
        <div class="card" style="text-align:center; padding: 24px; color: var(--color-ink-sub); font-size: 13px;">
            予定なし
        </div>
    @endif

    {{-- 公演共有マスタへのグローバル導線（spec §3の4タブは増やさず・当落タブ以外の入口を補う） --}}
    <div class="sec-label">公演マスタ</div>
    <div class="link-row">
        <a href="{{ route('events.index') }}" class="primary">公演一覧</a>
        <a href="{{ route('events.import') }}">貼り付けインポート</a>
    </div>

    {{-- 公演日を過ぎた予定（planned）の「参戦した？」確認（spec §5・自動遷移はしない） --}}
    @if($pendingConfirmations->isNotEmpty())
    <div class="sec-label">参戦した？</div>
    @foreach($pendingConfirmations as $attendance)
        @php $oshi = optional($attendance->fcMemberships->first())->oshi_color ?? '#C7414F'; @endphp
        <div class="confirm-card" style="--oshi-color: {{ $oshi }}">
            <div class="confirm-q">{{ $attendance->event_name }}</div>
            <div class="confirm-sub">
                {{ optional($attendance->event_date)->format('Y.m.d') }}（{{ optional($attendance->event_date)->translatedFormat('D') }}）
                @if($attendance->venue)・{{ $attendance->venue->name }}@endif
                。予定日が過ぎています。
            </div>
            <div class="confirm-actions">
                <form method="POST" action="{{ route('attendances.confirm', $attendance) }}" style="flex:1;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="decision" value="attended">
                    <button type="submit" class="yes" style="width:100%;">参戦した（記録する）</button>
                </form>
                <form method="POST" action="{{ route('attendances.confirm', $attendance) }}" style="flex:1;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="decision" value="skipped">
                    <button type="submit" style="width:100%;">行かなかった（スキップ）</button>
                </form>
            </div>
        </div>
    @endforeach
    @endif

    <div class="sec-label">積み上げ</div>
    <div class="ledger">
        <div><div class="v">{{ $stats['attended_count'] }}</div><div class="k">今年の参戦</div></div>
        <div><div class="v">{{ $stats['pending_lots'] }}</div><div class="k">当落待ち</div></div>
        <div><div class="v">{{ $stats['identity_count'] }}</div><div class="k">名義</div></div>
    </div>

    <div class="sec-label">直近の記録</div>
    @forelse($recentAttendances as $attendance)
        @php $oshi = optional($attendance->fcMemberships->first())->oshi_color ?? '#C7414F'; @endphp
        <a href="{{ route('attendances.show', $attendance) }}" class="rec">
            <div class="rec-head">
                <span class="dot" style="--oshi-color: {{ $oshi }}"></span>
                <div class="rec-title">{{ $attendance->event_name }}</div>
                <div class="rec-date">{{ optional($attendance->event_date)->format('m.d') }}</div>
            </div>
            <div class="rec-meta">
                @if($attendance->seat_raw)
                <span>座席 <b>{{ $attendance->seat_raw }}</b></span>
                @endif
                @if($attendance->companion)
                <span>同行 <b>{{ $attendance->companion }}</b></span>
                @endif
            </div>
        </a>
    @empty
        <div class="empty-state">まだ記録がありません</div>
    @endforelse
</x-app-layout>
