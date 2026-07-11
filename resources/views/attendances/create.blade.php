<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.index') }}" class="back">← 戻る</a>
            <h1>参戦を記録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- 公演を検索付きセレクトで選択（events共有マスタ）→ 日付・会場は自動表示 --}}
        @include('partials.event-cascade', ['selectedEvent' => null])

        {{-- 座席: 構造化3フィールドが主入力（spec §5-8） --}}
        <label class="form-label">座席</label>
        <div class="seat-fields" style="display:flex; gap:8px;">
            <input class="form-input" type="text" id="seat_block" name="seat_block"
                   value="{{ old('seat_block') }}" placeholder="ブロック" style="flex:2;">
            <input class="form-input" type="text" id="seat_row" name="seat_row"
                   value="{{ old('seat_row') }}" placeholder="列" style="flex:1;">
            <input class="form-input" type="text" id="seat_number" name="seat_number"
                   value="{{ old('seat_number') }}" placeholder="番" style="flex:1;">
        </div>

        <label class="form-label" for="seat_raw">座席表記（自動合成・編集可）</label>
        <input class="form-input" type="text" id="seat_raw" name="seat_raw"
               value="{{ old('seat_raw') }}" placeholder="アリーナ B4 3列 15番">

        <label class="form-label">ステータス</label>
        <select class="form-select" name="status">
            <option value="attended" {{ old('status', 'attended') === 'attended' ? 'selected' : '' }}>参戦済み</option>
            <option value="planned" {{ old('status') === 'planned' ? 'selected' : '' }}>参戦予定</option>
            <option value="skipped" {{ old('status') === 'skipped' ? 'selected' : '' }}>スキップ</option>
        </select>

        @if($memberships->isNotEmpty())
        <label class="form-label">名義（複数選択可）</label>
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
        @endif

        <label class="form-label" for="companion">同行者</label>
        <input class="form-input" type="text" id="companion" name="companion" value="{{ old('companion') }}">

        <label class="form-label" for="memo">メモ</label>
        <textarea class="form-textarea" id="memo" name="memo">{{ old('memo') }}</textarea>

        {{-- 写真添付（5枚まで・10MB/枚・EXIF除去して保存 / spec §4） --}}
        <label class="form-label" for="photos">写真（5枚まで・メンバー間で共有されます）</label>
        <input class="form-input @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror"
               type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
        @error('photos')<div class="form-error">{{ $message }}</div>@enderror
        @error('photos.*')<div class="form-error">{{ $message }}</div>@enderror

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">この参戦を記録する</button>
    </form>

@include('partials.seat-compose')
</x-app-layout>
