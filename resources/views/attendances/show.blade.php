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
        <div style="display:flex; align-items:center; gap:8px; padding:8px 0; font-size:13px; flex-wrap:wrap;">
            <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
            <span style="flex:1">{{ $m->displayName() }}</span>
            {{-- 当落結果の更新フォーム（S5/S6 の入力動線） --}}
            <form method="POST" action="{{ route('attendance-identities.update-result', $m->pivot->id) }}"
                  style="display:flex; align-items:center; gap:6px;">
                @csrf @method('PATCH')
                <select name="result" class="form-select" style="width:auto; padding:4px 28px 4px 10px; font-size:11px;">
                    <option value="pending" {{ $m->pivot->result === 'pending' ? 'selected' : '' }}>未発表</option>
                    <option value="won" {{ $m->pivot->result === 'won' ? 'selected' : '' }}>当選</option>
                    <option value="lost" {{ $m->pivot->result === 'lost' ? 'selected' : '' }}>落選</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm" style="padding:5px 12px;">更新</button>
            </form>
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

    {{-- 添付写真（メンバー間共有 / 削除は投稿者のみ） --}}
    @if($attendance->photos->isNotEmpty())
    <div class="detail-section">
        <div class="detail-label">写真</div>
        <div class="photo-thumbs">
            @foreach($attendance->photos as $photo)
            <span class="thumb">
                <img src="{{ route('photos.show', $photo) }}" alt="">
                @if($photo->user_id === auth()->id())
                <form method="POST" action="{{ route('photos.destroy', $photo) }}"
                      onsubmit="return confirm('この写真を削除しますか？')"
                      style="position:absolute; top:4px; right:4px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="copy-btn" style="padding:2px 7px; background:rgba(250,250,247,.9);">✕</button>
                </form>
                @endif
            </span>
            @endforeach
        </div>
    </div>
    @endif

    <div style="margin-top:24px;">
        {{-- won付き（昇格済み）は削除不可。当選履歴保全（spec §7 Q3） --}}
        @if($attendance->canBeDeleted())
        <form method="POST" action="{{ route('attendances.destroy', $attendance) }}"
              onsubmit="return confirm('この参戦記録を削除しますか？添付写真も削除されます。')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-secondary btn-sm" style="color:#C7414F;">削除</button>
        </form>
        @else
        <p style="font-size:11px; color:var(--color-ink-sub); line-height:1.7;">
            当選済みの記録は削除できません。行かなかった場合は編集画面でステータスを「スキップ」に変更してください。
        </p>
        @endif
    </div>
</x-app-layout>
