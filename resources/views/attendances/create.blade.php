<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.index') }}" class="back">← 戻る</a>
            <h1>参戦を記録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label class="form-label" for="event_date">日付</label>
            <input class="form-input @error('event_date') is-invalid @enderror"
                   type="date" id="event_date" name="event_date"
                   value="{{ old('event_date', now()->format('Y-m-d')) }}" required>
            @error('event_date')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="event_name">公演名</label>
            <input class="form-input @error('event_name') is-invalid @enderror"
                   type="text" id="event_name" name="event_name"
                   value="{{ old('event_name') }}" required autocomplete="off"
                   placeholder="例: LUMIÈRE LIVE TOUR 2026 横浜">
            <div id="event-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
            @error('event_name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="venue_name">会場</label>
            <input class="form-input" type="text" id="venue_name" name="venue_name"
                   value="{{ old('venue_name') }}" placeholder="会場名を入力" autocomplete="off">
            <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id') }}">
            <div id="venue-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
        </div>

        <div class="form-group" id="venue-address-wrap" style="{{ old('venue_id') ? 'display:none;' : '' }}">
            <label class="form-label" for="venue_address">会場住所（新規会場のみ・自動補完）</label>
            <input class="form-input" type="text" id="venue_address" name="venue_address"
                   value="{{ old('venue_address') }}" placeholder="空欄可">
        </div>

        {{-- 座席: 構造化3フィールドが主入力（spec §5-8） --}}
        <div class="form-group">
            <label class="form-label">座席</label>
            <div style="display:flex; gap:8px;">
                <input class="form-input" type="text" id="seat_block" name="seat_block"
                       value="{{ old('seat_block') }}" placeholder="ブロック" style="flex:2;">
                <input class="form-input" type="text" id="seat_row" name="seat_row"
                       value="{{ old('seat_row') }}" placeholder="列" style="flex:1;">
                <input class="form-input" type="text" id="seat_number" name="seat_number"
                       value="{{ old('seat_number') }}" placeholder="番" style="flex:1;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="seat_raw">座席表記（自動合成・編集可）</label>
            <input class="form-input" type="text" id="seat_raw" name="seat_raw"
                   value="{{ old('seat_raw') }}" placeholder="例: アリーナ B4 3列 15番">
        </div>

        <div style="display:flex; gap:12px;">
            <div class="form-group" style="flex:1">
                <label class="form-label" for="open_time">開場</label>
                <input class="form-input" type="time" id="open_time" name="open_time" value="{{ old('open_time') }}">
            </div>
            <div class="form-group" style="flex:1">
                <label class="form-label" for="start_time">開演</label>
                <input class="form-input" type="time" id="start_time" name="start_time" value="{{ old('start_time') }}">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">ステータス</label>
            <select class="form-select" name="status">
                <option value="attended" {{ old('status', 'attended') === 'attended' ? 'selected' : '' }}>参戦済み</option>
                <option value="planned" {{ old('status') === 'planned' ? 'selected' : '' }}>参戦予定</option>
                <option value="applied" {{ old('status') === 'applied' ? 'selected' : '' }}>申込中</option>
                <option value="skipped" {{ old('status') === 'skipped' ? 'selected' : '' }}>スキップ</option>
            </select>
        </div>

        @if($memberships->isNotEmpty())
        <div class="form-group">
            <label class="form-label">名義</label>
            <div class="form-checkbox-group">
                @foreach($memberships as $m)
                <label class="form-checkbox-label">
                    <input type="checkbox" name="identity_ids[]" value="{{ $m->id }}"
                           {{ in_array($m->id, old('identity_ids', [])) ? 'checked' : '' }}>
                    <span class="dot" style="--oshi-color: {{ $m->oshi_color ?? '#C7414F' }}"></span>
                    {{ $m->displayName() }}
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="form-group">
            <label class="form-label" for="companion">同行者</label>
            <input class="form-input" type="text" id="companion" name="companion" value="{{ old('companion') }}">
        </div>

        <div class="form-group">
            <label class="form-label" for="memo">メモ</label>
            <textarea class="form-textarea" id="memo" name="memo">{{ old('memo') }}</textarea>
        </div>

        {{-- 写真添付（5枚まで・10MB/枚・EXIF除去して保存 / spec §4） --}}
        <div class="form-group">
            <label class="form-label" for="photos">写真（5枚まで・メンバー間で共有されます）</label>
            <input class="form-input @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
                   type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
            @error('photos')<div class="form-error">{{ $message }}</div>@enderror
            @error('photos.*')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary">保存する</button>
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
