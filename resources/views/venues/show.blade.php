<x-app-layout :hide-fab="true">
    <button type="button" class="detail-back" onclick="history.back()">‹ 戻る</button>

    {{-- 会場ヒーロー（mockup .venue-hero） --}}
    <div class="venue-hero">
        <div class="vh-name">{{ $venue->name }}</div>
        <div class="vh-sub">
            @if($venue->nearest_station){{ $venue->nearest_station }}@endif
            @if($venue->nearest_station && $venue->capacity) ・ @endif
            @if($venue->capacity)キャパ約{{ number_format($venue->capacity) }}@endif
            @if(!$venue->nearest_station && !$venue->capacity)会場情報は未登録@endif
        </div>
        @if($venue->address)
        <div class="vh-sub" style="margin-top:4px;">{{ $venue->address }}</div>
        @endif
    </div>

    {{-- 自分のメモ（編集可・d-block内の f-field） --}}
    <div class="d-block">
        <div class="d-h">自分のメモ</div>
        <form method="POST" action="{{ route('venues.update-note', $venue) }}">
            @csrf @method('PUT')
            <div class="f-field">
                <label for="lodging">定宿・ホテルエリア</label>
                <input class="f-input" type="text" id="lodging" name="lodging"
                       value="{{ old('lodging', $note->lodging ?? '') }}" placeholder="未記入">
            </div>
            <div class="f-field">
                <label for="transport_cost">交通費目安</label>
                <input class="f-input" type="text" id="transport_cost" name="transport_cost"
                       value="{{ old('transport_cost', $note->transport_cost ?? '') }}" placeholder="未記入">
            </div>
            <div class="f-field" style="margin-bottom:12px;">
                <label for="memo">個人メモ</label>
                <textarea class="f-input" id="memo" name="memo" rows="3" placeholder="未記入" style="resize:vertical;">{{ old('memo', $note->memo ?? '') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">メモを保存</button>
        </form>
    </div>

    {{-- 見え方マッピング（全メンバーの写真を座席情報つきタイルで・mockup .view-tile） --}}
    <div class="sec-label">見え方マッピング（メンバー全員の記録）</div>
    @forelse($photos as $photo)
    <div class="view-tile">
        <img class="view-photo" src="{{ route('photos.show', $photo) }}" alt="" loading="lazy">
        <div class="view-cap">
            <span class="view-seat">{{ $photo->attendance?->seat_raw ?: '座席未記入' }}</span>
            <span class="view-by">
                by {{ $photo->user->name }}
                @if($photo->user_id === auth()->id())
                <form method="POST" action="{{ route('photos.destroy', $photo) }}"
                      onsubmit="return confirm('この写真を削除しますか？')" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="copy-btn" style="padding:2px 8px;">削除</button>
                </form>
                @endif
            </span>
        </div>
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">まだ写真がありません</div>
    @endforelse

    {{-- この会場の参戦履歴 --}}
    @if($attendances->isNotEmpty())
    <div class="sec-label">この会場の参戦履歴</div>
    @foreach($attendances as $attendance)
        @php $oshi = optional($attendance->fcMemberships->first())->oshi_color ?? '#C7414F'; @endphp
        <a href="{{ route('attendances.show', $attendance) }}" class="rec">
            <div class="rec-head">
                <span class="dot" style="--oshi-color: {{ $oshi }}"></span>
                <div class="rec-title">{{ $attendance->event_name }}</div>
                <div class="rec-date">{{ optional($attendance->event_date)->format('Y.m.d') }}</div>
            </div>
            @if($attendance->seat_raw)
            <div class="rec-meta"><span>座席 <b>{{ $attendance->seat_raw }}</b></span></div>
            @endif
        </a>
    @endforeach
    @endif
</x-app-layout>
