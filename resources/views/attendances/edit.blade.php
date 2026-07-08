<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.show', $attendance) }}" class="back">← 戻る</a>
            <h1>参戦記録を編集</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.update', $attendance) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="form-group">
            <label class="form-label" for="event_date">日付</label>
            <input class="form-input @error('event_date') is-invalid @enderror"
                   type="date" id="event_date" name="event_date"
                   value="{{ old('event_date', $attendance->event_date->format('Y-m-d')) }}" required>
            @error('event_date')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="event_name">公演名</label>
            <input class="form-input @error('event_name') is-invalid @enderror"
                   type="text" id="event_name" name="event_name"
                   value="{{ old('event_name', $attendance->event_name) }}" required autocomplete="off">
            <div id="event-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
            @error('event_name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="venue_name">会場</label>
            <input class="form-input" type="text" id="venue_name" name="venue_name"
                   value="{{ old('venue_name', optional($attendance->venue)->name) }}" autocomplete="off">
            <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id', $attendance->venue_id) }}">
            <div id="venue-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
        </div>

        <div class="form-group" id="venue-address-wrap" style="display:none;">
            <label class="form-label" for="venue_address">会場住所（新規会場のみ・自動補完）</label>
            <input class="form-input" type="text" id="venue_address" name="venue_address"
                   value="{{ old('venue_address') }}" placeholder="空欄可">
        </div>

        {{-- 座席: 構造化3フィールドが主入力（spec §5-8） --}}
        <div class="form-group">
            <label class="form-label">座席</label>
            <div style="display:flex; gap:8px;">
                <input class="form-input" type="text" id="seat_block" name="seat_block"
                       value="{{ old('seat_block', $attendance->seat_block) }}" placeholder="ブロック" style="flex:2;">
                <input class="form-input" type="text" id="seat_row" name="seat_row"
                       value="{{ old('seat_row', $attendance->seat_row) }}" placeholder="列" style="flex:1;">
                <input class="form-input" type="text" id="seat_number" name="seat_number"
                       value="{{ old('seat_number', $attendance->seat_number) }}" placeholder="番" style="flex:1;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="seat_raw">座席表記（自動合成・編集可）</label>
            <input class="form-input" type="text" id="seat_raw" name="seat_raw"
                   value="{{ old('seat_raw', $attendance->seat_raw) }}">
        </div>

        <div style="display:flex; gap:12px;">
            <div class="form-group" style="flex:1">
                <label class="form-label" for="open_time">開場</label>
                <input class="form-input" type="time" id="open_time" name="open_time"
                       value="{{ old('open_time', $attendance->open_time ? \Carbon\Carbon::parse($attendance->open_time)->format('H:i') : '') }}">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label" for="start_time">開演</label>
                <input class="form-input" type="time" id="start_time" name="start_time"
                       value="{{ old('start_time', $attendance->start_time ? \Carbon\Carbon::parse($attendance->start_time)->format('H:i') : '') }}">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ステータス</label>
            <select class="form-select" name="status">
                @foreach(['attended' => '参戦済み', 'planned' => '参戦予定', 'applied' => '申込中', 'skipped' => 'スキップ'] as $val => $label)
                <option value="{{ $val }}" {{ old('status', $attendance->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if($memberships->isNotEmpty())
        @php $selectedIds = old('identity_ids', $attendance->fcMemberships->pluck('id')->toArray()); @endphp
        <div class="form-group">
            <label class="form-label">名義</label>
            <div class="form-checkbox-group">
                @foreach($memberships as $m)
                <label class="form-checkbox-label">
                    <input type="checkbox" name="identity_ids[]" value="{{ $m->id }}"
                           {{ in_array($m->id, $selectedIds) ? 'checked' : '' }}>
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="form-group">
            <label class="form-label" for="companion">同行者</label>
            <input class="form-input" type="text" id="companion" name="companion"
                   value="{{ old('companion', $attendance->companion) }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="memo">メモ</label>
            <textarea class="form-textarea" id="memo" name="memo">{{ old('memo', $attendance->memo) }}</textarea>
        </div>

        {{-- 既存写真 + 追加アップロード（合計5枚まで） --}}
        @if($attendance->photos->isNotEmpty())
        <div class="form-group">
            <label class="form-label">添付済みの写真（削除は詳細画面から）</label>
            <div class="photo-thumbs">
                @foreach($attendance->photos as $photo)
                <span class="thumb"><img src="{{ route('photos.show', $photo) }}" alt=""></span>
                @endforeach
            </div>
        </div>
        @endif

        @if($attendance->photos->count() < \App\Services\PhotoService::MAX_PHOTOS_PER_ATTENDANCE)
        <div class="form-group">
            <label class="form-label" for="photos">写真を追加（合計5枚まで）</label>
            <input class="form-input @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
                   type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
            @error('photos')<div class="form-error">{{ $message }}</div>@enderror
            @error('photos.*')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        @endif

        <button type="submit" class="btn btn-primary">更新する</button>
    </form>
</x-app-layout>

@push('scripts')
@include('partials.suggest-scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    setupVenueField();
    setupEventNameField();
    setupSeatCompose();
});
</script>
@endpush
