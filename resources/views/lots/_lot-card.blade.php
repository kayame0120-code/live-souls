{{-- 当落カード（名義行ごとに result 更新可 / spec §5-7-2） --}}
<div class="lot">
    <div class="lot-head">
        <div>
            <a href="{{ route('attendances.show', $attendance) }}" style="color:inherit; text-decoration:none;">
                <div class="lot-title">{{ $attendance->event_name }}</div>
            </a>
            <div class="lot-sub">
                {{ $attendance->event_date->format('m.d') }}（{{ $attendance->event_date->translatedFormat('D') }}）
                @if($attendance->venue)・{{ $attendance->venue->name }}@endif
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
                  style="display:flex; align-items:center; gap:6px;">
                @csrf @method('PATCH')
                <select name="result" class="form-select" style="width:auto; padding:3px 26px 3px 8px; font-size:10.5px;"
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
