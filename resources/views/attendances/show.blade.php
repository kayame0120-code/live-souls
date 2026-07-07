<x-app-layout :hide-header="true" :hide-fab="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.index') }}" class="back">← 戻る</a>
            <h1>参戦詳細</h1>
            <div class="actions">
                <a href="{{ route('attendances.edit', $attendance) }}" class="btn btn-secondary btn-sm">編集</a>
            </div>
        </div>
    </x-slot:pageHeader>

    <div class="detail-section">
        <div class="detail-label">公演名</div>
        <div class="detail-value">{{ $attendance->event_name }}</div>
    </div>

    <div class="detail-section">
        <div style="display:flex; gap:24px;">
            <div>
                <div class="detail-label">日付</div>
                <div class="detail-value">{{ $attendance->event_date->format('Y.m.d') }}（{{ $attendance->event_date->translatedFormat('D') }}）</div>
            </div>
            <div>
                <div class="detail-label">ステータス</div>
                <span class="status-badge status-{{ $attendance->status }}">
                    {{ ['attended' => '参戦済み', 'planned' => '参戦予定', 'applied' => '申込中', 'skipped' => 'スキップ'][$attendance->status] }}
                </span>
            </div>
        </div>
    </div>

    @if($attendance->venue)
    <div class="detail-section">
        <div class="detail-label">会場</div>
        <div class="detail-value">
            <a href="{{ route('venues.show', $attendance->venue) }}" style="color:inherit; text-decoration:underline; text-underline-offset:3px;">
                {{ $attendance->venue->name }}
            </a>
        </div>
    </div>
    @endif

    @if($attendance->open_time || $attendance->start_time)
    <div class="detail-section">
        <div style="display:flex; gap:24px;">
            @if($attendance->open_time)
            <div>
                <div class="detail-label">開場</div>
                <div class="detail-value">{{ \Carbon\Carbon::parse($attendance->open_time)->format('H:i') }}</div>
            </div>
            @endif
            @if($attendance->start_time)
            <div>
                <div class="detail-label">開演</div>
                <div class="detail-value">{{ \Carbon\Carbon::parse($attendance->start_time)->format('H:i') }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    @if($attendance->seat_raw)
    <div class="detail-section">
        <div class="detail-label">座席</div>
        <div class="detail-value">{{ $attendance->seat_raw }}</div>
    </div>
    @endif

    @if($attendance->fcMemberships->isNotEmpty())
    <div class="detail-section">
        <div class="detail-label">名義・当落</div>
        @foreach($attendance->fcMemberships as $m)
        <div style="display:flex; align-items:center; gap:8px; padding:8px 0; font-size:13px;">
            <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
            <span style="flex:1">{{ $m->displayName() }}</span>
            <span class="st {{ ['won'=>'win','lost'=>'lose','pending'=>'wait'][$m->pivot->result] }}">
                {{ ['won'=>'当選','lost'=>'落選','pending'=>'未発表'][$m->pivot->result] }}
            </span>
        </div>
        @endforeach
    </div>
    @endif

    @if($attendance->companion)
    <div class="detail-section">
        <div class="detail-label">同行者</div>
        <div class="detail-value">{{ $attendance->companion }}</div>
    </div>
    @endif

    @if($attendance->memo)
    <div class="detail-section">
        <div class="detail-label">メモ</div>
        <div class="rec-note" style="margin:4px 0 0 0;">{{ $attendance->memo }}</div>
    </div>
    @endif

    <div style="margin-top:24px;">
        <form method="POST" action="{{ route('attendances.destroy', $attendance) }}"
              onsubmit="return confirm('この参戦記録を削除しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-secondary btn-sm" style="color:#C7414F;">削除</button>
        </form>
    </div>
</x-app-layout>
