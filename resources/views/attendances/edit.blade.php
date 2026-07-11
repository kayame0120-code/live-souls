<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.show', $attendance) }}" class="back">← 戻る</a>
            <h1>参戦記録を編集</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.update', $attendance) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        {{-- 公演は検索付きセレクト（現在の公演を初期選択） --}}
        @include('partials.event-cascade', ['selectedEvent' => $attendance->event])

        {{-- 座席: 構造化3フィールドが主入力（spec §5-8） --}}
        <label class="form-label">座席</label>
        <div class="seat-fields" style="display:flex; gap:8px;">
            <input class="form-input" type="text" id="seat_block" name="seat_block"
                   value="{{ old('seat_block', $attendance->seat_block) }}" placeholder="ブロック" style="flex:2;">
            <input class="form-input" type="text" id="seat_row" name="seat_row"
                   value="{{ old('seat_row', $attendance->seat_row) }}" placeholder="列" style="flex:1;">
            <input class="form-input" type="text" id="seat_number" name="seat_number"
                   value="{{ old('seat_number', $attendance->seat_number) }}" placeholder="番" style="flex:1;">
        </div>

        <label class="form-label" for="seat_raw">座席表記（自動合成・編集可）</label>
        <input class="form-input" type="text" id="seat_raw" name="seat_raw"
               value="{{ old('seat_raw', $attendance->seat_raw) }}">

        <label class="form-label">ステータス</label>
        <select class="form-select" name="status">
            @foreach(['attended' => '参戦済み', 'planned' => '参戦予定', 'applied' => '申込中', 'skipped' => 'スキップ'] as $val => $label)
            <option value="{{ $val }}" {{ old('status', $attendance->status) === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        @if($memberships->isNotEmpty())
        @php $selectedIds = old('identity_ids', $attendance->fcMemberships->pluck('id')->toArray()); @endphp
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
        @endif

        <label class="form-label" for="companion">同行者</label>
        <input class="form-input" type="text" id="companion" name="companion"
               value="{{ old('companion', $attendance->companion) }}">

        <label class="form-label" for="memo">メモ</label>
        <textarea class="form-textarea" id="memo" name="memo">{{ old('memo', $attendance->memo) }}</textarea>

        {{-- 既存写真 + 追加アップロード（合計5枚まで） --}}
        @if($attendance->photos->isNotEmpty())
        <label class="form-label">添付済みの写真（削除は詳細画面から）</label>
        <div class="photo-thumbs">
            @foreach($attendance->photos as $photo)
            <span class="thumb"><img src="{{ route('photos.show', $photo) }}" alt=""></span>
            @endforeach
        </div>
        @endif

        @if($attendance->photos->count() < \App\Services\PhotoService::MAX_PHOTOS_PER_ATTENDANCE)
        <label class="form-label" for="photos">写真を追加（合計5枚まで）</label>
        <input class="form-input @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
               type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
        @error('photos')<div class="form-error">{{ $message }}</div>@enderror
        @error('photos.*')<div class="form-error">{{ $message }}</div>@enderror
        @endif

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">更新する</button>
    </form>

@include('partials.seat-compose')
</x-app-layout>
