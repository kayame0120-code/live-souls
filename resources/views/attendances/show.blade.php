<x-app-layout :hide-fab="true">
    <div class="detail-topbar">
        <a href="{{ route('attendances.index') }}" class="detail-back">‹ 参戦記録へ戻る</a>
        <a href="{{ route('attendances.edit', $attendance) }}" class="detail-edit">編集</a>
    </div>

    @php $firstM = $attendance->fcMemberships->first(); @endphp
    <div class="att-hero" style="--oshi-color: {{ $firstM->oshi_color ?? '#C7414F' }}">
        <div class="att-date">{{ optional($attendance->event_date)->format('Y.m.d') }}（{{ optional($attendance->event_date)->translatedFormat('D') }}）</div>
        <div class="att-title">{{ $attendance->event_name }}</div>
        @if($firstM)
        <span class="att-meigi">
            <span class="dot" style="--oshi-color: {{ $firstM->oshi_color ?? '#C7414F' }}"></span>
            {{ $firstM->displayName() }}
        </span>
        @endif
    </div>

    {{-- 公演情報（共有マスタ）。開演は event.start_time（H:i・null非表示）。開場(open_time)行は廃止（mockup準拠） --}}
    <div class="d-block">
        <div class="d-h">公演情報（共有マスタ）</div>
        @if($attendance->venue)
        <div class="d-row">
            <span class="k">会場</span>
            <span class="v"><a href="{{ route('venues.show', $attendance->venue) }}" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">{{ $attendance->venue->name }}</a></span>
        </div>
        @endif
        @if($attendance->venue?->nearest_station)
        <div class="d-row"><span class="k">最寄</span><span class="v">{{ $attendance->venue->nearest_station }}</span></div>
        @endif
        @if($attendance->event?->start_time)
        <div class="d-row"><span class="k">開演</span><span class="v mono">{{ $attendance->event->start_time->format('H:i') }}</span></div>
        @endif
    </div>

    {{-- 座席・記録 --}}
    <div class="d-block">
        <div class="d-h">座席・記録</div>
        <div class="d-row">
            <span class="k">ステータス</span>
            <span class="v"><span class="status-badge status-{{ $attendance->status }}">{{ ['attended' => '参戦済み', 'planned' => '参戦予定', 'applied' => '申込中', 'skipped' => 'スキップ'][$attendance->status] }}</span></span>
        </div>
        @if($attendance->seat_raw)
        <div class="d-row"><span class="k">座席</span><span class="v">{{ $attendance->seat_raw }}</span></div>
        @endif
        @if($attendance->companion)
        <div class="d-row"><span class="k">同行</span><span class="v">{{ $attendance->companion }}</span></div>
        @endif
    </div>

    {{-- 名義・当落（申込名義ごとに result 更新。ロジックは不変） --}}
    @if($attendance->fcMemberships->isNotEmpty())
    <div class="d-block">
        <div class="d-h">名義・当落</div>
        @foreach($attendance->fcMemberships as $m)
        <div class="apply-row">
            <div class="ar-body">
                <div class="ar-name">
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}
                </div>
            </div>
            <form method="POST" action="{{ route('attendance-identities.update-result', $m->pivot->id) }}"
                  style="display:flex; align-items:center; gap:6px;">
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
    @endif

    {{-- メモ --}}
    @if($attendance->memo)
    <div class="d-block">
        <div class="d-h">メモ</div>
        <p style="font-size:12.5px; line-height:1.8; color:var(--color-ink);">{{ $attendance->memo }}</p>
    </div>
    @endif

    {{-- この日の写真（メンバー間共有 / 削除は投稿者のみ） --}}
    @if($attendance->photos->isNotEmpty())
    <div class="d-block">
        <div class="d-h">この日の写真（{{ $attendance->photos->count() }}）</div>
        <div class="thumb-grid">
            @foreach($attendance->photos as $photo)
            <div class="thumb">
                <img src="{{ route('photos.show', $photo) }}" alt="">
                @if($photo->user_id === auth()->id())
                <form method="POST" action="{{ route('photos.destroy', $photo) }}"
                      onsubmit="return confirm('この写真を削除しますか？')"
                      style="position:absolute; top:4px; right:4px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="copy-btn" style="padding:2px 7px; background:rgba(250,250,247,.9);">✕</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 会場のビュー（見え方マッピング）への導線。360°(C)は対象外・通常の会場詳細へ --}}
    @if($attendance->venue)
    <a href="{{ route('venues.show', $attendance->venue) }}" class="venue-view-btn">
        <span>この会場のビューを見る</span>
        <small>{{ $attendance->venue->name }} ・ 見え方マッピング</small>
    </a>
    @endif

    {{-- 削除（won付きは不可・spec §7 Q3）。ロジック不変 --}}
    <div style="margin-top:14px;">
        @if($attendance->canBeDeleted())
        <form method="POST" action="{{ route('attendances.destroy', $attendance) }}"
              onsubmit="return confirm('この参戦記録を削除しますか？添付写真も削除されます。')">
            @csrf @method('DELETE')
            <button type="submit" class="f-danger">この参戦記録を削除する</button>
        </form>
        @else
        <p style="font-size:11px; color:var(--color-ink-sub); line-height:1.7; text-align:center;">
            当選済みの記録は削除できません。行かなかった場合は編集でステータスを「スキップ」に。
        </p>
        @endif
    </div>
</x-app-layout>
