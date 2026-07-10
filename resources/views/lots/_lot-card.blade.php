{{-- 当落カード（名義行ごとに result 更新可 / spec §5-7-2） --}}
<div class="lot">
    <div class="lot-head">
        <div>
            {{-- ツアー詳細内なので日程（会場＋ラベル）を見出しにする。ツアー名はヒーロー側 --}}
            <a href="{{ route('attendances.show', $attendance) }}" style="color:inherit; text-decoration:none;">
                <div class="lot-title">
                    {{ $attendance->venue?->name ?? '会場未設定' }}@if($attendance->event?->event_label) {{ $attendance->event->event_label }}@endif
                </div>
            </a>
            <div class="lot-sub">
                {{ optional($attendance->event_date)->format('m.d') }}（{{ optional($attendance->event_date)->translatedFormat('D') }}）
                @if($attendance->event?->start_time)・開演 {{ $attendance->event->start_time->format('H:i') }}@endif
            </div>


        </div>
    </div>
    <div class="lot-rows">
        @foreach($attendance->fcMemberships as $m)
        <div class="lot-row">
            <span class="who">
                <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                {{ $m->displayName() }}・{{ $m->pivot->ticket_count }}枚
            </span>
            <form method="POST" action="{{ route('attendance-identities.update-result', $m->pivot->id) }}"
                  style="display:flex; align-items:center;">
                @csrf @method('PATCH')
                <select name="result" class="lot-select" data-v="{{ $m->pivot->result }}"
                        onchange="this.form.submit()">
                    <option value="pending" {{ $m->pivot->result === 'pending' ? 'selected' : '' }}>未発表</option>
                    <option value="won" {{ $m->pivot->result === 'won' ? 'selected' : '' }}>当選</option>
                    <option value="lost" {{ $m->pivot->result === 'lost' ? 'selected' : '' }}>落選</option>
                </select>
            </form>
        </div>
        @endforeach
    </div>
</div>
