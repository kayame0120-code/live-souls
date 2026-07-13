{{-- 会場の検索付きセレクト（既存venues部分一致→無ければ新規＝Placesオートフィル / spec §5-11）
     hidden venue_id + venue_name（新規名）+ venue_address（新規時のみ・Places自動補完）。 --}}
<label class="form-label" for="venue_name">会場（検索付き・無ければ新規登録）</label>
<div class="combo">
    <input class="form-input" type="text" id="venue_name" name="venue_name"
           value="{{ old('venue_name', $selectedVenueName ?? '') }}" placeholder="会場名で検索" autocomplete="off">
    <input type="hidden" id="venue_id" name="venue_id" value="{{ old('venue_id', $selectedVenueId ?? '') }}">
    <div class="combo-list" id="venue-list" style="display:none;"></div>
</div>
<div class="form-group" id="venue-address-wrap" style="{{ old('venue_id', $selectedVenueId ?? '') ? 'display:none;' : '' }}">
    <label class="form-label" for="venue_address">会場住所（新規会場のみ・自動補完）</label>
    <input class="form-input" type="text" id="venue_address" name="venue_address"
           value="{{ old('venue_address') }}" placeholder="空欄可">
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('venue_name');
    const hidden = document.getElementById('venue_id');
    const list = document.getElementById('venue-list');
    const addressWrap = document.getElementById('venue-address-wrap');
    const addressInput = document.getElementById('venue_address');
    if (!input) return;
    let timer;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        hidden.value = '';
        if (addressWrap) addressWrap.style.display = 'block';
        const q = this.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(() => {
            fetch('{{ route('api.venues.suggest') }}?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(venues => {
                    list.textContent = '';
                    if (!venues.length) { list.style.display = 'none'; return; }
                    venues.forEach(v => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = v.name;
                        if (v.address) {
                            const sub = document.createElement('div');
                            sub.className = 'sub';
                            sub.textContent = v.address;
                            b.appendChild(sub);
                        }
                        b.addEventListener('click', () => {
                            input.value = v.name;
                            hidden.value = v.id;
                            if (addressWrap) addressWrap.style.display = 'none';
                            list.style.display = 'none';
                        });
                        list.appendChild(b);
                    });
                    list.style.display = 'block';
                })
                .catch(() => { list.style.display = 'none'; });
        }, 250);
    });

    // 新規会場のとき名称確定（blur）でPlacesオートフィル（失敗・キー無しは手入力のまま）
    if (addressInput) {
        input.addEventListener('blur', () => {
            setTimeout(() => {
                const q = input.value.trim();
                if (!q || hidden.value || addressInput.value) return;
                fetch('{{ route('api.venues.place-lookup') }}?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        const first = (data.results || [])[0];
                        if (first && first.address && !addressInput.value) addressInput.value = first.address;
                    })
                    .catch(() => {});
            }, 250);
        });
    }

    document.addEventListener('click', function (e) {
        if (!list.contains(e.target) && e.target !== input) list.style.display = 'none';
    });
});
</script>
