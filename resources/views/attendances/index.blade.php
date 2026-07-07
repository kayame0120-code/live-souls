<x-app-layout>
    <div class="filter-row">
        @foreach($years as $y)
            <a href="{{ route('attendances.index', ['year' => $y]) }}"
               class="chip {{ ($year ?? (string)now()->year) === $y ? 'on' : '' }}">{{ $y }}</a>
        @endforeach
        <a href="{{ route('attendances.index', ['year' => 'all']) }}"
           class="chip {{ ($year ?? '') === 'all' ? 'on' : '' }}">すべて</a>
    </div>

    @forelse($attendances as $attendance)
        @php
            $oshi = optional($attendance->fcMemberships->first())->oshi_color ?? '#C7414F';
            $isSkipped = $attendance->status === 'skipped';
        @endphp
        <a href="{{ route('attendances.show', $attendance) }}"
           class="rec" @if($isSkipped) style="opacity: 0.5" @endif>
            <div class="rec-head">
                <span class="dot" style="--oshi-color: {{ $oshi }}"></span>
                <div class="rec-title">{{ $attendance->event_name }}</div>
                <div class="rec-date">{{ $attendance->event_date->format('Y.m.d') }}</div>
            </div>
            <div class="rec-meta">
                @if($attendance->venue)
                <span>会場 <b>{{ $attendance->venue->name }}</b></span>
                @endif
                @if($attendance->seat_raw)
                <span>座席 <b>{{ $attendance->seat_raw }}</b></span>
                @endif
                @if($attendance->fcMemberships->isNotEmpty())
                <span>名義 <b>{{ $attendance->fcMemberships->first()->displayName() }}</b></span>
                @endif
                @if($attendance->companion)
                <span>同行 <b>{{ $attendance->companion }}</b></span>
                @endif
            </div>
            @if($attendance->memo)
            <div class="rec-note">{{ $attendance->memo }}</div>
            @endif
        </a>
    @empty
        <div class="empty-state">
            まだ参戦記録がありません。<br>
            ＋から最初の1件を記帳しましょう
        </div>
    @endforelse
</x-app-layout>
