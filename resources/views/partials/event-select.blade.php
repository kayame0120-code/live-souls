{{-- 公演の検索付きセレクト（events共有マスタ・spec §5・T7）
     $selectedEvent（nullable Event）を渡すと初期選択を復元する。
     選択で hidden の event_id を埋め、日付・会場・アクセスを読取表示する。 --}}
@php $sel = $selectedEvent ?? null; @endphp
<label class="form-label" for="ev-search">公演</label>
<div class="combo">
    <input class="form-input @error('event_id') is-invalid @enderror" type="text" id="ev-search"
           placeholder="公演名で検索（例: Prism）" autocomplete="off"
           value="{{ $sel?->event_name }}">
    <input type="hidden" name="event_id" id="event_id" value="{{ old('event_id', $sel?->id) }}">
    <div class="combo-list" id="ev-list" style="display:none;"></div>
</div>
<div class="ev-auto" id="ev-auto" style="{{ $sel ? '' : 'display:none;' }}">
    <span class="tag">選択中の公演（自動表示・読取）</span>
    <div>日付 <b id="ev-date">{{ $sel ? $sel->event_date->format('Y.m.d') : '—' }}</b></div>
    <div>会場 <b id="ev-venue">{{ $sel?->venue?->name ?? '—' }}</b></div>
</div>
<p class="combo-empty" id="ev-nohit" style="display:none;">
    該当する公演がありません。<a href="{{ route('events.create') }}">＋ 公演を新規登録</a>
</p>
@error('event_id')<div class="form-error">{{ $message }}</div>@enderror

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('ev-search');
    const hidden = document.getElementById('event_id');
    const list = document.getElementById('ev-list');
    const auto = document.getElementById('ev-auto');
    const noHit = document.getElementById('ev-nohit');
    if (!search) return;
    let timer;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        hidden.value = '';
        auto.style.display = 'none';
        const q = this.value.trim();
        if (q.length < 1) { list.style.display = 'none'; noHit.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch('{{ route('api.events.suggest') }}?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(events => {
                    list.textContent = '';
                    noHit.style.display = events.length === 0 ? 'block' : 'none';
                    if (events.length === 0) { list.style.display = 'none'; return; }
                    events.forEach(e => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = e.event_name;
                        const sub = document.createElement('div');
                        sub.className = 'sub';
                        sub.textContent = e.event_date + (e.venue_name ? '・' + e.venue_name : '');
                        b.appendChild(sub);
                        b.addEventListener('click', () => {
                            search.value = e.event_name;
                            hidden.value = e.id;
                            document.getElementById('ev-date').textContent = e.event_date;
                            document.getElementById('ev-venue').textContent = e.venue_name || '—';
                            auto.style.display = 'block';
                            list.style.display = 'none';
                            noHit.style.display = 'none';
                        });
                        list.appendChild(b);
                    });
                    list.style.display = 'block';
                })
                .catch(() => { list.style.display = 'none'; });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!list.contains(e.target) && e.target !== search) list.style.display = 'none';
    });
});
</script>
@endpush
