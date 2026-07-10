<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('tours.show', $tour) }}" class="detail-back">‹ {{ $tour->name }} へ戻る</a>

    <div class="sec-label">日程を追加</div>
    <p style="font-size:12px; color:var(--color-ink-sub); margin-bottom:12px;">ツアー「{{ $tour->name }}」に日程を追加します。</p>

    @if(session('duplicate_warning'))
    <div class="warn">{{ session('duplicate_warning') }}</div>
    @endif

    <form method="POST" action="{{ route('events.store', $tour) }}">
        @csrf

        <div class="f-field">
            <label for="event_date">公演日</label>
            <input class="f-input @error('event_date') is-invalid @enderror" type="date" id="event_date" name="event_date"
                   value="{{ old('event_date') }}" required>
            @error('event_date')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        {{-- 開演時間（任意）。同日昼夜は開演を変えて別日程として登録（§4 events start_time） --}}
        <div class="f-field">
            <label for="start_time">開演時間 <span class="opt">（任意）</span></label>
            <input class="f-input @error('start_time') is-invalid @enderror" type="time" id="start_time" name="start_time"
                   value="{{ old('start_time') }}">
            @error('start_time')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        {{-- 任意ラベル（日程を区別・例「大阪 2日目」「追加公演 名古屋」「昼公演」）。ツアー名は書かない --}}
        <div class="f-field">
            <label for="event_label">日程ラベル <span class="opt">（任意）</span></label>
            <input class="f-input" type="text" id="event_label" name="event_label"
                   value="{{ old('event_label') }}" placeholder="例：大阪 2日目 / 追加公演 名古屋 / 昼公演">
        </div>

        @include('partials.venue-select')

        <div class="f-field">
            <label for="application_deadline">申込締切 <span class="opt">（任意）</span></label>
            <input class="f-input" type="datetime-local" id="application_deadline" name="application_deadline"
                   value="{{ old('application_deadline') }}">
        </div>

        <div class="f-field">
            <label for="announce_date">当落発表日 <span class="opt">（任意）</span></label>
            <input class="f-input" type="date" id="announce_date" name="announce_date"
                   value="{{ old('announce_date') }}">
        </div>

        @if(session('duplicate_warning'))
        <input type="hidden" name="confirm_duplicate" value="1">
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">重複を承知で登録する</button>
        @else
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">この日程を登録する</button>
        @endif
    </form>
</x-app-layout>
