<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.import') }}" class="detail-back">‹ やり直す</a>
    <div class="sec-label">解析結果</div>

    @if(empty($rows))
        <div class="empty-state">解析できる公演がありませんでした。テキストを確認してやり直してください。</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); margin-bottom:12px;">
        {{ count($rows) }}件の公演が見つかりました。内容を確認して登録してください。
    </p>

    <form method="POST" action="{{ route('events.import.store') }}">
        @csrf
        @if(request('idol_group_id'))<input type="hidden" name="idol_group_id" value="{{ request('idol_group_id') }}">@endif

        <div class="f-field">
            <label for="tour_name">ツアー名</label>
            <div class="combo">
                <input class="f-input" type="text" id="tour_name" name="tour_name" autocomplete="off"
                       value="{{ old('tour_name', $tour) }}" placeholder="ツアー名" required>
                <div class="combo-list" id="tour-list" style="display:none;"></div>
            </div>
            <div class="f-hint">既存ツアーに一致すればそこへ追加、無ければ新規作成されます。</div>
        </div>

        @foreach($rows as $i => $row)
        @php $hasDate = !empty($row['event_date']); @endphp
        <div class="d-block" style="margin-bottom:6px;padding:10px 14px;{{ $hasDate ? '' : 'opacity:0.5;' }}">
            <input type="hidden" name="rows[{{ $i }}][include]" value="{{ $hasDate ? '1' : '0' }}">
            <input type="hidden" name="rows[{{ $i }}][event_date]" value="{{ $row['event_date'] ?? '' }}">
            <input type="hidden" name="rows[{{ $i }}][start_time]" value="{{ $row['start_time'] ?? '' }}">
            <input type="hidden" name="rows[{{ $i }}][venue_name]" value="{{ $row['venue'] ?? '' }}">
            <input type="hidden" name="rows[{{ $i }}][event_label]" value="{{ $row['event_label'] ?? '' }}">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:13px;font-weight:600;">{{ $row['event_date'] ?? '日付不明' }}@if(!empty($row['start_time'])) {{ $row['start_time'] }}@endif</div>
                    <div style="font-size:12px;color:var(--color-ink-sub);">{{ $row['venue'] ?? '会場不明' }}@if(!empty($row['event_label'])) ・{{ $row['event_label'] }}@endif</div>
                </div>
                @if($hasDate)
                <span style="font-size:11px;color:#43A047;">登録</span>
                @else
                <span style="font-size:11px;color:var(--color-ink-sub);">スキップ</span>
                @endif
            </div>
        </div>
        @endforeach

        @if(!empty($deadlines))
        <div class="sec-label" style="margin-top:18px;">申込締切・当落発表日</div>
        @foreach($deadlines as $j => $dl)
        <div class="d-block" style="margin-bottom:6px;padding:10px 14px;">
            <input type="hidden" name="deadlines[{{ $j }}][label]" value="{{ $dl['label'] ?? '' }}">
            <input type="hidden" name="deadlines[{{ $j }}][application_deadline]" value="{{ $dl['application_deadline'] ?? '' }}">
            <input type="hidden" name="deadlines[{{ $j }}][announce_date]" value="{{ $dl['announce_date'] ?? '' }}">
            <div style="font-size:13px;font-weight:600;">{{ $dl['label'] ?? '先行名なし' }}</div>
            <div style="font-size:12px;color:var(--color-ink-sub);">
                @if(!empty($dl['application_deadline']))締切 {{ $dl['application_deadline'] }}@endif
                @if(!empty($dl['announce_date'])) ・発表 {{ $dl['announce_date'] }}@endif
            </div>
        </div>
        @endforeach
        @endif

        @php $eventCount = count(array_filter($rows, fn($r) => !empty($r['event_date']))); @endphp
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">{{ $eventCount }}件の公演{{ !empty($deadlines) ? '＋締切' . count($deadlines) . '件' : '' }}を登録する</button>
    </form>
    @endif

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('tour_name');
    const list = document.getElementById('tour-list');
    if (!input) return;
    let timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch('{{ route('api.tours.suggest') }}?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(tours => {
                    list.textContent = '';
                    if (!tours.length) { list.style.display = 'none'; return; }
                    tours.forEach(t => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = t.name;
                        b.addEventListener('click', () => { input.value = t.name; list.style.display = 'none'; });
                        list.appendChild(b);
                    });
                    list.style.display = 'block';
                })
                .catch(() => { list.style.display = 'none'; });
        }, 250);
    });
    document.addEventListener('click', e => { if (!list.contains(e.target) && e.target !== input) list.style.display = 'none'; });
});
</script>
</x-app-layout>
