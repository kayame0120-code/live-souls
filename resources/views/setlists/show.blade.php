<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('tours.show', $tour) }}" class="detail-back">‹ 公演詳細へ戻る</a>

    <div class="venue-hero">
        <div class="vh-name">セットリスト</div>
        <div class="vh-sub">{{ $tour->name }}</div>
    </div>

    @forelse($tour->setlists as $setlist)
    <div class="d-block" style="margin-bottom:12px;">
        @if($setlist->label)
        <div class="d-h">{{ $setlist->label }}</div>
        @endif
        @foreach($setlist->items as $item)
        <div class="d-row" style="display:flex;align-items:center;gap:10px;">
            <span class="k" style="min-width:28px;text-align:center;font-weight:600;">{{ $item->display_label ?? str_pad($item->sort_order, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="v" style="flex:1;">{{ $item->title }}</span>
            <form method="POST" action="{{ route('setlists.destroy-item', [$tour, $item]) }}"
                  onsubmit="return confirm('この曲を削除しますか？')">
                @csrf @method('DELETE')
                <button type="submit" class="copy-btn" style="font-size:11px;padding:2px 8px;">削除</button>
            </form>
        </div>
        @endforeach
    </div>
    @empty
    <div class="empty-state" style="padding:24px;">まだセットリストがありません</div>
    @endforelse

    <div class="d-block" style="margin-top:16px;">
        <div class="d-h">曲を追加</div>
        <form method="POST" action="{{ route('setlists.add-item', $tour) }}" style="display:flex;gap:8px;align-items:flex-end;">
            @csrf
            @if($tour->setlists->isEmpty())
            <input type="hidden" name="label" value="">
            @else
            <input type="hidden" name="setlist_id" value="{{ $tour->setlists->first()->id }}">
            @endif
            <div style="flex:0 0 50px;">
                <input class="f-input" type="text" name="display_label" placeholder="EN" style="text-align:center;font-size:12px;">
            </div>
            <div style="flex:1;">
                <input class="f-input" type="text" name="title" placeholder="曲名" required>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">追加</button>
        </form>
    </div>

    {{-- 一括登録（AI / JSON） --}}
    <div class="d-block" style="margin-top:16px;">
        <div class="d-h">セトリを一括登録</div>
        @if(session('error'))
        <div class="warn">{{ session('error') }}</div>
        @endif

        <div class="fc-tabs" style="margin-bottom:10px;">
            <button type="button" class="fc-tab on" id="stab-ai">AI解析</button>
            <button type="button" class="fc-tab" id="stab-json">JSON貼り付け</button>
        </div>

        @if(isset($aiItems))
        <form method="POST" action="{{ route('setlists.bulk-store', $tour) }}">
            @csrf
            @foreach($aiItems as $i => $item)
            <div class="imp-row" style="display:flex;gap:8px;align-items:center;padding:6px 0;">
                <input type="hidden" name="items[{{ $i }}][include]" value="1">
                <span style="min-width:24px;text-align:center;font-size:12px;font-weight:600;">{{ $item['order'] ?? $i + 1 }}</span>
                <input type="hidden" name="items[{{ $i }}][display_label]" value="{{ $item['note'] ?? '' }}">
                <input type="hidden" name="items[{{ $i }}][title]" value="{{ $item['title'] ?? '' }}">
                <span style="flex:1;font-size:13px;">{{ $item['title'] ?? '' }}</span>
                @if(!empty($item['note']))<span style="font-size:11px;color:var(--color-ink-sub);">{{ $item['note'] }}</span>@endif
            </div>
            @endforeach
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">{{ count($aiItems) }}曲を登録する</button>
        </form>
        @else
        <div id="spane-ai">
            <form method="POST" action="{{ route('setlists.ai-parse', $tour) }}" enctype="multipart/form-data" id="setlist-ai-form">
                @csrf
                <div style="border:2px dashed var(--color-keisen-strong);border-radius:12px;padding:16px;text-align:center;margin-bottom:8px;cursor:pointer;" onclick="document.getElementById('setlist-images').click()">
                    <div style="font-size:12px;font-weight:600;">セトリ画像をドロップ / クリック</div>
                    <div style="font-size:11px;color:var(--color-ink-sub);">jpeg/png/webp・最大5枚</div>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" id="setlist-images">
                </div>
                <details>
                    <summary style="font-size:12px;color:var(--color-ink-sub);cursor:pointer;">テキストで入力</summary>
                    <textarea class="f-input" name="text" rows="5" placeholder="セットリストのテキストを貼り付け" style="margin-top:6px;">{{ old('text') }}</textarea>
                </details>
                <button type="submit" class="btn btn-primary" id="setlist-ai-btn" style="margin-top:8px;">AI解析する</button>
                <div id="setlist-loading" style="display:none;text-align:center;padding:16px;">
                    <div style="font-size:13px;font-weight:600;">AI解析中...</div>
                </div>
            </form>
        </div>
        <div id="spane-json" style="display:none;">
            <form method="POST" action="{{ route('setlists.json-import', $tour) }}" enctype="multipart/form-data">
                @csrf
                <div class="f-field" style="margin-bottom:6px;">
                    <textarea class="f-input" name="json_text" rows="5" placeholder='{"items":[{"order":1,"title":"曲名","note":null}]}'>{{ old('json_text') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:8px;">読み込む</button>
            </form>
        </div>
        @endif
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var stabAi = document.getElementById('stab-ai');
    var stabJson = document.getElementById('stab-json');
    var spaneAi = document.getElementById('spane-ai');
    var spaneJson = document.getElementById('spane-json');
    if (stabAi && stabJson && spaneAi && spaneJson) {
        stabAi.addEventListener('click', function () {
            stabAi.classList.add('on'); stabJson.classList.remove('on');
            spaneAi.style.display = ''; spaneJson.style.display = 'none';
        });
        stabJson.addEventListener('click', function () {
            stabJson.classList.add('on'); stabAi.classList.remove('on');
            spaneJson.style.display = ''; spaneAi.style.display = 'none';
        });
    }

    var form = document.getElementById('setlist-ai-form');
    var btn = document.getElementById('setlist-ai-btn');
    var loading = document.getElementById('setlist-loading');
    if (form && btn && loading) {
        form.addEventListener('submit', function () {
            btn.disabled = true; btn.style.display = 'none'; loading.style.display = '';
        });
    }
});
</script>
</x-app-layout>
