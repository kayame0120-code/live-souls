<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.import') }}" class="detail-back">‹ やり直す</a>
    <div class="sec-label">解析結果の確認</div>

    @if(empty($rows))
        <div class="empty-state">解析できる公演がありませんでした。形式を確認してください</div>
    @else
    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
        内容を確認・修正してください。日付が空の行は取込対象外です（背景色つき）。<br>
        同日でも開演が違えば別公演（昼夜）として登録されます。名義選択はありません。
    </p>

    <form method="POST" action="{{ route('events.import.store') }}">
        @csrf

        {{-- ★v1.4：ツアー名の解決（全行共通・1箇所）。既存tourを検索・無ければ新規作成 --}}
        <div class="f-field">
            <label for="tour_name">ツアー名（全日程に適用）</label>
            <div class="combo">
                <input class="f-input" type="text" id="tour_name" name="tour_name" autocomplete="off"
                       value="{{ old('tour_name', $tour) }}" placeholder="ツアー名を入力・既存を検索" required>
                <div class="combo-list" id="tour-list" style="display:none;"></div>
            </div>
            <div class="f-hint">既存ツアーに一致すればそこへ、無ければ新規ツアーとして作成されます。</div>
        </div>

        <div class="imp-head"><span>日付 / 開演</span><span>会場 / ラベル</span><span></span></div>
        @foreach($rows as $i => $row)
        @php $invalid = empty($row['event_date']); @endphp
        <div class="imp-row {{ $invalid ? 'imp-row-invalid' : '' }}">
            <input type="hidden" name="rows[{{ $i }}][include]" value="0">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <div style="display:flex; align-items:center; gap:4px;">
                    <input type="checkbox" name="rows[{{ $i }}][include]" value="1" {{ $invalid ? '' : 'checked' }}>
                    <input class="form-input" type="date" name="rows[{{ $i }}][event_date]" value="{{ $row['event_date'] }}">
                </div>
                <input class="form-input" type="time" name="rows[{{ $i }}][start_time]" value="{{ $row['start_time'] }}" style="max-width:120px;">
            </div>
            <div>
                <input class="form-input" type="text" name="rows[{{ $i }}][venue_name]" value="{{ $row['venue'] }}" placeholder="会場">
                <input class="form-input" type="text" name="rows[{{ $i }}][event_label]" value="" placeholder="日程ラベル（任意・例 大阪2日目）" style="margin-top:4px;">
            </div>
            <span></span>
        </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="margin-top:18px;">チェックした行を共有マスタへ登録</button>
    </form>
    @endif

    {{-- 未解析行（捨てず保持・spec §5「未解析行」） --}}
    @if(!empty($unknown))
    <div style="margin-top:20px; padding:12px 14px; background:#FBF3F2; border:1px solid #EBD3D3; border-radius:6px;">
        <div style="font-size:11.5px; color:var(--color-ink-sub); margin-bottom:6px;">未解析として保持された行（捨てていません・確認用）:</div>
        @foreach($unknown as $u)
        <div style="font-family:monospace; font-size:10.5px; color:var(--color-ink-sub);">・{{ $u }}</div>
        @endforeach
    </div>
    @endif
</x-app-layout>

@push('scripts')
<script>
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
@endpush
