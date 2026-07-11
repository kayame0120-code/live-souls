<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('tours.show', $event->tour) }}" class="detail-back">‹ {{ $event->tour->name }} へ戻る</a>

    <div class="sec-label">日程を編集</div>

    <form method="POST" action="{{ route('events.update', $event) }}">
        @csrf @method('PUT')

        <div class="f-field">
            <label for="event_date">公演日</label>
            <input class="f-input @error('event_date') is-invalid @enderror" type="date" id="event_date" name="event_date"
                   value="{{ old('event_date', $event->event_date->format('Y-m-d')) }}" required>
            @error('event_date')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="f-field">
            <label for="start_time">開演時間 <span class="opt">（任意）</span></label>
            <input class="f-input @error('start_time') is-invalid @enderror" type="time" id="start_time" name="start_time"
                   value="{{ old('start_time', optional($event->start_time)->format('H:i')) }}">
            @error('start_time')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="f-field">
            <label for="event_label">日程ラベル <span class="opt">（任意）</span></label>
            <input class="f-input" type="text" id="event_label" name="event_label"
                   value="{{ old('event_label', $event->event_label) }}" placeholder="例：大阪 2日目 / 昼公演">
        </div>

        @include('partials.venue-select', [
            'selectedVenueId' => old('venue_id', $event->venue_id),
            'selectedVenueName' => old('venue_name', $event->venue?->name),
        ])

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">保存する</button>
    </form>
</x-app-layout>
