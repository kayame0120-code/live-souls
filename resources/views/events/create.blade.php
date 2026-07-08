<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('events.index') }}" class="back">← 戻る</a>
            <h1>公演を登録</h1>
        </div>
    </x-slot:pageHeader>

    @if(session('duplicate_warning'))
    <div class="warn">{{ session('duplicate_warning') }}</div>
    @endif

    <form method="POST" action="{{ route('events.store') }}">
        @csrf

        <label class="form-label" for="event_name">公演名</label>
        <input class="form-input @error('event_name') is-invalid @enderror" type="text" id="event_name" name="event_name"
               value="{{ old('event_name') }}" required placeholder="例: LUMIÈRE「Prism of Night」">
        @error('event_name')<div class="form-error">{{ $message }}</div>@enderror

        <label class="form-label" for="event_date">公演日</label>
        <input class="form-input @error('event_date') is-invalid @enderror" type="date" id="event_date" name="event_date"
               value="{{ old('event_date') }}" required>
        @error('event_date')<div class="form-error">{{ $message }}</div>@enderror

        {{-- ★v1.3：開演時間（任意）。同日昼夜は開演を変えて別公演として登録する（§4 events start_time）。 --}}
        <label class="form-label" for="start_time">開演時間（任意）</label>
        <input class="form-input @error('start_time') is-invalid @enderror" type="time" id="start_time" name="start_time"
               value="{{ old('start_time') }}">
        @error('start_time')<div class="form-error">{{ $message }}</div>@enderror

        @include('partials.venue-select')

        {{-- 重複警告後の続行フラグ（同一会場×同一日付でも昼夜2公演なら続行OK） --}}
        @if(session('duplicate_warning'))
        <input type="hidden" name="confirm_duplicate" value="1">
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">重複を承知で登録する</button>
        @else
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">共有マスタに登録する</button>
        @endif
    </form>
</x-app-layout>
