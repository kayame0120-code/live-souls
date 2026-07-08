{{-- 公演のカスケード選択（v1.5・spec §5）：①ツアー→②日程（event）。
     $tours（Tour一覧）を渡す。②はツアー選択時に api.tours.events から非同期取得。
     hidden の event_id に②の選択値を入れる。$selectedEvent（nullable）で初期選択を復元。 --}}
@php $selEvent = $selectedEvent ?? null; $selTourId = $selEvent?->tour_id; @endphp
<div class="f-field">
    <label for="tour_select">① ツアー</label>
    <select class="f-input" id="tour_select">
        <option value="">選択してください</option>
        @foreach($tours as $t)
        <option value="{{ $t->id }}" {{ old('tour_id', $selTourId) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
        @endforeach
    </select>
    <div class="f-hint">
        目的のツアーが無い場合は
        <a href="{{ route('tours.create') }}" style="color:var(--color-ink);font-weight:700;">＋ ツアーを追加</a>
    </div>
</div>

<div class="f-field">
    <label for="event_select">② 日程</label>
    <select class="f-input @error('event_id') is-invalid @enderror" id="event_select" name="event_id" required
            {{ $selTourId ? '' : 'disabled' }}>
        <option value="">① を先に選んでください</option>
    </select>
    <div class="f-hint" id="event-empty-hint" style="display:none;">
        このツアーに日程がありません。
        <a href="#" id="add-event-link" style="color:var(--color-ink);font-weight:700;">＋ 日程を追加</a>
    </div>
    @error('event_id')<div class="form-error">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tourSel = document.getElementById('tour_select');
    const eventSel = document.getElementById('event_select');
    const emptyHint = document.getElementById('event-empty-hint');
    const addLink = document.getElementById('add-event-link');
    if (!tourSel || !eventSel) return;

    const preselectEventId = @json(old('event_id', $selEvent?->id));

    function loadEvents(tourId, preselect) {
        eventSel.innerHTML = '';
        emptyHint.style.display = 'none';
        if (!tourId) {
            eventSel.disabled = true;
            eventSel.appendChild(new Option('① を先に選んでください', ''));
            return;
        }
        if (addLink) addLink.href = '/tours/' + tourId + '/events/create';
        fetch('/api/tours/' + tourId + '/events')
            .then(r => r.json())
            .then(events => {
                eventSel.innerHTML = '';
                if (!events.length) {
                    eventSel.disabled = true;
                    eventSel.appendChild(new Option('日程がありません', ''));
                    emptyHint.style.display = 'block';
                    return;
                }
                eventSel.disabled = false;
                eventSel.appendChild(new Option('選択してください', ''));
                events.forEach(e => {
                    const opt = new Option(e.label, e.id);
                    if (preselect && String(preselect) === String(e.id)) opt.selected = true;
                    eventSel.appendChild(opt);
                });
            })
            .catch(() => { eventSel.disabled = true; });
    }

    tourSel.addEventListener('change', () => loadEvents(tourSel.value, null));

    // 初期復元（編集・バリデーション差し戻し時）
    if (tourSel.value) loadEvents(tourSel.value, preselectEventId);
});
</script>
@endpush
