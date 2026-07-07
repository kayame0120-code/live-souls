<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('attendances.index') }}" class="back">← 戻る</a>
            <h1>参戦を記録</h1>
        </div>
    </x-slot:pageHeader>

    <form method="POST" action="{{ route('attendances.store') }}">
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
                   value="{{ old('event_name') }}" required placeholder="例: LUMIÈRE LIVE TOUR 2026 横浜">
            @error('event_name')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="venue_name">会場</label>
            <input class="form-input" type="text" id="venue_name" name="venue_name"
                   value="{{ old('venue_name') }}" placeholder="会場名を入力" autocomplete="off">
            <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id') }}">
            <div id="venue-suggestions" style="display:none; background:var(--color-card); border:1px solid var(--color-keisen); border-radius:10px; margin-top:4px; overflow:hidden;"></div>
        </div>

        <div class="form-group">
            <label class="form-label" for="seat_raw">座席</label>
            <input class="form-input" type="text" id="seat_raw" name="seat_raw"
                   value="{{ old('seat_raw') }}" placeholder="例: アリーナ B4ブロック 3列 15番">
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

        <button type="submit" class="btn btn-primary">保存する</button>
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
