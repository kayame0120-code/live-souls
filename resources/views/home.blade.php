<x-app-layout>
    <div class="sec-label">次の現場</div>
    @if($nextAttendance)
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
                @if($nextAttendance->start_time)
                <span>開演 <b>{{ \Carbon\Carbon::parse($nextAttendance->start_time)->format('H:i') }}</b></span>
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
                <div class="rec-date">{{ $attendance->event_date->format('m.d') }}</div>
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
