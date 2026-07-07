<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.show', $attendance) }}" class="back">← 戻る</a>
            <h1>参戦記録を編集</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.update', $attendance) }}">
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
                   value="{{ old('event_name', $attendance->event_name) }}" required>
            @error('event_name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="venue_name">会場</label>
            <input class="form-input" type="text" id="venue_name" name="venue_name"
                   value="{{ old('venue_name', optional($attendance->venue)->name) }}" autocomplete="off">
            <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id', $attendance->venue_id) }}">
            <div id="venue-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
        </div>

        <div class="form-group">
            <label class="form-label" for="seat_raw">座席</label>
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

        <button type="submit" class="btn btn-primary">更新する</button>
    </form>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('venue_name');
    const hiddenId = document.getElementById('venue_id');
    const sugBox = document.getElementById('venue-suggestions');
    let timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        hiddenId.value = '';
        const q = this.value.trim();
        if (q.length < 1) { sugBox.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch('/api/venues/suggest?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(venues => {
                    if (venues.length === 0) { sugBox.style.display = 'none'; return; }
                    sugBox.textContent = '';
                    venues.forEach(v => {
                        const d = document.createElement('div');
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-keisen)';
                        d.dataset.id = v.id;
                        d.dataset.name = v.name;
                        d.textContent = v.name;
                        if (v.address) {
                            const span = document.createElement('span');
                            span.style.cssText = 'color:var(--color-ink-sub);font-size:11px;margin-left:6px';
                            span.textContent = v.address;
                            d.appendChild(span);
                        }
                        d.addEventListener('click', () => {
                            input.value = d.dataset.name;
                            hiddenId.value = d.dataset.id;
                            sugBox.style.display = 'none';
                        });
                        sugBox.appendChild(d);
                    });
                    sugBox.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!sugBox.contains(e.target) && e.target !== input) sugBox.style.display = 'none';
    });
});
</script>
@endpush
