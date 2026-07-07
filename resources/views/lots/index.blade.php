<x-app-layout>
    @if($pending->isEmpty() && $decided->isEmpty())
        <div class="empty-state">当落待ちの申込はありません</div>
    @else
        @if($pending->isNotEmpty())
        <div class="sec-label">当落待ち</div>
        @foreach($pending as $attendance)
        <div class="lot">
            <div class="lot-head">
                <div>
                    <div class="lot-title">{{ $attendance->event_name }}</div>
                    <div class="lot-sub">{{ $attendance->event_date->format('m.d') }}（{{ $attendance->event_date->translatedFormat('D') }}）</div>
                </div>
            </div>
            <div class="lot-rows">
                @foreach($attendance->fcMemberships as $m)
                <div class="lot-row">
                    <span class="who">
                        <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                        {{ $m->displayName() }}・{{ $m->pivot->ticket_count }}枚
                    </span>
                    <span class="st {{ ['won'=>'win','lost'=>'lose','pending'=>'wait'][$m->pivot->result] }}">
                        {{ ['won'=>'当選','lost'=>'落選','pending'=>'未発表'][$m->pivot->result] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endif

        @if($decided->isNotEmpty())
        <div class="sec-label">結果</div>
        @foreach($decided as $attendance)
        <div class="lot">
            <div class="lot-head">
                <div>
                    <div class="lot-title">{{ $attendance->event_name }}</div>
                    <div class="lot-sub">{{ $attendance->event_date->format('m.d') }}（{{ $attendance->event_date->translatedFormat('D') }}）</div>
                </div>
            </div>
            <div class="lot-rows">
                @foreach($attendance->fcMemberships as $m)
                <div class="lot-row">
                    <span class="who">
                        <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                        {{ $m->displayName() }}・{{ $m->pivot->ticket_count }}枚
                    </span>
                    <span class="st {{ ['won'=>'win','lost'=>'lose','pending'=>'wait'][$m->pivot->result] }}">
                        {{ ['won'=>'当選','lost'=>'落選','pending'=>'未発表'][$m->pivot->result] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endif
    @endif
</x-app-layout>
