{{-- 会場・公演名サジェスト + Places オートフィル + 座席自動合成の共用スクリプト --}}
<script>
// サジェストボックスを安全に構築する（XSS対策: textContent のみ使用）
// mapItem: APIの1件を {label, sub, ...} に整形する関数
function buildSuggestBox(input, box, fetchUrl, mapItem, onPick) {
    let timer;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 1) { box.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch(fetchUrl + encodeURIComponent(q))
                .then(r => r.json())
                .then(raw => {
                    const items = raw.map(mapItem);
                    if (!items.length) { box.style.display = 'none'; return; }
                    box.textContent = '';
                    items.forEach(item => {
                        const d = document.createElement('div');
                        d.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-keisen)';
                        d.textContent = item.label;
                        if (item.sub) {
                            const span = document.createElement('span');
                            span.style.cssText = 'color:var(--color-ink-sub);font-size:11px;margin-left:6px';
                            span.textContent = item.sub;
                            d.appendChild(span);
                        }
                        d.addEventListener('click', () => { onPick(item); box.style.display = 'none'; });
                        box.appendChild(d);
                    });
                    box.style.display = 'block';
                })
                .catch(() => { box.style.display = 'none'; });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!box.contains(e.target) && e.target !== input) box.style.display = 'none';
    });
}

// 会場サジェスト（既存venue選択 or 新規名）+ 新規時のPlacesオートフィル
function setupVenueField() {
    const input = document.getElementById('venue_name');
    const hiddenId = document.getElementById('venue_id');
    const box = document.getElementById('venue-suggestions');
    const addressWrap = document.getElementById('venue-address-wrap');
    const addressInput = document.getElementById('venue_address');
    if (!input || !box) return;

    input.addEventListener('input', () => {
        hiddenId.value = '';
        if (addressWrap) addressWrap.style.display = 'block';
    });

    buildSuggestBox(
        input, box,
        '{{ route('api.venues.suggest') }}?q=',
        v => ({ label: v.name, sub: v.address || '', id: v.id }),
        item => {
            input.value = item.label;
            hiddenId.value = item.id;
            // 既存会場を選んだら住所入力は不要
            if (addressWrap) addressWrap.style.display = 'none';
        },
    );

    // 新規会場のとき、名称確定（blur）で Places オートフィル（失敗・キー未設定時は手入力のまま）
    if (addressInput) {
        input.addEventListener('blur', () => {
            setTimeout(() => {
                const q = input.value.trim();
                if (!q || hiddenId.value || addressInput.value) return;
                fetch('{{ route('api.venues.place-lookup') }}?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        const first = (data.results || [])[0];
                        if (first && first.address && !addressInput.value) {
                            addressInput.value = first.address;
                        }
                    })
                    .catch(() => {});
            }, 250);
        });
    }
}

// 公演名サジェスト（自分＋メンバーの過去入力 / 規約0-6③）
function setupEventNameField() {
    const input = document.getElementById('event_name');
    const box = document.getElementById('event-suggestions');
    if (!input || !box) return;

    buildSuggestBox(
        input, box,
        '{{ route('api.events.suggest') }}?q=',
        e => ({ label: e.event_name, sub: e.event_date ? e.event_date.substring(0, 10) : '' }),
        item => { input.value = item.label; },
    );
}

// 座席3フィールド → seat_raw 自動合成（手動編集後は上書きしない / spec §5-8）
function setupSeatCompose() {
    const block = document.getElementById('seat_block');
    const row = document.getElementById('seat_row');
    const number = document.getElementById('seat_number');
    const raw = document.getElementById('seat_raw');
    if (!block || !raw) return;

    const compose = () => {
        const parts = [];
        if (block.value.trim()) parts.push(block.value.trim());
        if (row.value.trim()) parts.push(row.value.trim() + '列');
        if (number.value.trim()) parts.push(number.value.trim() + '番');
        return parts.join(' ');
    };

    // 初期状態: seat_raw が構造化フィールドの合成結果と一致すれば自動モード、差異があれば手動モード
    let manual = raw.value !== '' && raw.value !== compose();

    raw.addEventListener('input', () => { manual = true; });

    [block, row, number].forEach(el => el.addEventListener('input', () => {
        if (!manual) raw.value = compose();
    }));
}
</script>
