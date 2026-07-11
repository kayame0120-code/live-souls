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

    @if($pendingConfirmations->isNotEmpty() || $ticketReminders->isNotEmpty())
    <div class="sec-label">確認が必要</div>
    @foreach($pendingConfirmations as $attendance)
        <div class="confirm-card">
            <div class="confirm-lead">この公演、参戦した？</div>
            <div class="confirm-title">{{ $attendance->event_name }}</div>
            <div class="confirm-sub">
                {{ optional($attendance->event_date)->format('Y.m.d') }}（{{ optional($attendance->event_date)->translatedFormat('D') }}）
                @if($attendance->fcMemberships->isNotEmpty())・{{ $attendance->fcMemberships->first()->displayName() }}@endif
            </div>
            <div class="confirm-actions">
                <form method="POST" action="{{ route('attendances.confirm', $attendance) }}" style="flex:1;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="decision" value="attended">
                    <button type="submit" class="btn-attended" style="width:100%;">参戦した</button>
                </form>
                <form method="POST" action="{{ route('attendances.confirm', $attendance) }}" style="flex:1;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="decision" value="skipped">
                    <button type="submit" class="btn-skipped" style="width:100%;">行けなかった</button>
                </form>
            </div>
        </div>
    @endforeach
    @foreach($ticketReminders as $attendance)
        <div class="confirm-card">
            <div class="confirm-lead">チケット確認はお済みですか？</div>
            <div class="confirm-title">{{ $attendance->event_name }}</div>
            <div class="confirm-sub">
                {{ optional($attendance->event_date)->format('Y.m.d') }}（{{ optional($attendance->event_date)->translatedFormat('D') }}）
                @if($attendance->fcMemberships->isNotEmpty())・{{ $attendance->fcMemberships->first()->displayName() }}@endif
            </div>
        </div>
    @endforeach
    @endif

    @if($renewalMemberships->isNotEmpty())
    <div class="sec-label">更新期間の名義</div>
    @foreach($renewalMemberships as $membership)
        @php
            $expiry = $membership->expiryDate();
            $renewStart = $membership->renewalWindowStart();
            $oshi = $membership->oshi_color ?? '#C7414F';
        @endphp
        <div class="renew-card" style="--oshi-color: {{ $oshi }};position:relative;">
            <form method="POST" action="{{ route('renewals.dismiss', $membership) }}" style="position:absolute;top:10px;right:12px;">
                @csrf
                <button type="submit" class="copy-btn" style="font-size:10px;padding:2px 8px;">更新済み</button>
            </form>
            <div class="renew-top">
                <span class="renew-swatch"></span>
                <div class="renew-name">{{ $membership->person->name }}@if($membership->label)<small>{{ $membership->group?->name }}・{{ $membership->label }}</small>@else<small>{{ $membership->group?->name }}</small>@endif</div>
            </div>
            <div class="renew-body">
                更新受付中 <b>{{ $renewStart?->format('Y.m.d') }} – {{ $expiry?->format('m.d') }}</b> ／ 有効期限 <b>{{ $expiry?->format('Y.m.d') }}</b>
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

    <div class="sec-label" style="margin-top:16px;">設定</div>
    <a href="{{ route('settings.security') }}" class="rec" style="display:flex;justify-content:space-between;align-items:center;text-decoration:none;">
        <span style="font-size:13px;">🔐 セキュリティ設定（2段階認証）</span>
        <span style="color:var(--color-ink-sub);">›</span>
    </a>
</x-app-layout>
