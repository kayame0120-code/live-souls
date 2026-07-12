<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('events.index') }}" class="back">← 戻る</a>
            <h1>一覧を貼って一括登録</h1>
        </div>
    </x-slot:pageHeader>

    @if(session('error'))
    <div class="warn">{{ session('error') }}</div>
    @endif

    {{-- タブ切替 --}}
    <div class="fc-tabs" style="margin-bottom:16px;">
        <button type="button" class="fc-tab on" id="tab-ai">AI解析</button>
        <button type="button" class="fc-tab" id="tab-json">JSONアップロード</button>
    </div>

    {{-- AI解析（画像主・テキスト従） --}}
    <div id="pane-ai">
        <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
            公演スケジュールのスクショや画像をアップロードすると、AIが日付・会場・公演名に分解します。
        </p>
        <form method="POST" action="{{ route('events.import.parse') }}" enctype="multipart/form-data">
            @csrf
            <div id="ai-drop-zone" style="border:2px dashed var(--color-keisen-strong);border-radius:12px;padding:24px;text-align:center;margin-bottom:12px;cursor:pointer;transition:border-color .2s,background .2s;">
                <div style="font-size:13px;font-weight:600;margin-bottom:4px;">画像をドロップ または クリックで選択</div>
                <div style="font-size:11px;color:var(--color-ink-sub);">jpeg / png / webp・最大5枚</div>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" id="ai-image-input">
                <div id="ai-file-list" style="margin-top:8px;font-size:12px;color:var(--color-ink);"></div>
            </div>
            @error('images')<div class="form-error">{{ $message }}</div>@enderror
            @error('images.*')<div class="form-error">{{ $message }}</div>@enderror

            <details style="margin-bottom:12px;">
                <summary style="font-size:12px;color:var(--color-ink-sub);cursor:pointer;">テキストで入力する場合はこちら</summary>
                <textarea class="form-textarea" id="text" name="text" rows="6"
                          placeholder="公演情報のテキストをそのまま貼り付け" style="margin-top:8px;">{{ old('text') }}</textarea>
            </details>

            <button type="submit" class="btn btn-primary" id="parse-btn">AI解析する</button>
            <div id="loading" style="display:none;text-align:center;padding:24px;">
                <div style="font-size:14px;font-weight:600;">AI解析中...</div>
                <div style="font-size:12px;color:var(--color-ink-sub);margin-top:6px;">少々お待ちください</div>
            </div>
        </form>
    </div>

    {{-- JSONアップロード --}}
    <div id="pane-json" style="display:none;">
        <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
            ChatGPTやClaudeで作成したJSONファイルをアップロード、またはJSON文字列を貼り付けてください。
        </p>
        <div class="d-block" style="padding:10px 14px;margin-bottom:12px;font-size:11px;color:var(--color-ink-sub);line-height:1.8;">
            <div class="d-h">公演JSON</div>
            <code style="font-size:10px;display:block;white-space:pre;overflow-x:auto;">{"tour":"ツアー名","events":[{"event_label":null,"event_date":"YYYY-MM-DD","start_time":"HH:MM","venue":"会場名"}]}</code>
            <div class="d-h" style="margin-top:8px;">セットリストJSON</div>
            <code style="font-size:10px;display:block;white-space:pre;overflow-x:auto;">{"tour":"ツアー名","items":[{"order":1,"title":"曲名","note":null}]}</code>
            <div style="margin-top:6px;">公演・セットリストを同時にアップロードできます</div>
        </div>
        <form method="POST" action="{{ route('events.import.json') }}" enctype="multipart/form-data" id="json-form">
            @csrf
            <div id="drop-zone" style="border:2px dashed var(--color-keisen-strong);border-radius:12px;padding:24px;text-align:center;margin-bottom:12px;cursor:pointer;transition:border-color .2s,background .2s;">
                <div style="font-size:13px;font-weight:600;margin-bottom:4px;">ここにJSONファイルをドロップ</div>
                <div style="font-size:11px;color:var(--color-ink-sub);">または クリックでファイル選択（複数可）</div>
                <input type="file" name="json_files[]" accept=".json,.txt" multiple style="display:none;" id="json-file-input">
                <div id="drop-file-list" style="margin-top:8px;font-size:12px;color:var(--color-ink);"></div>
            </div>
            <div class="f-field">
                <label>または JSON文字列を貼り付け</label>
                <textarea class="form-textarea" name="json_text" rows="6" placeholder='{"tour":"...","events":[...]}'>{{ old('json_text') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">読み込む</button>
        </form>
    </div>


<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    var tabAi = document.getElementById('tab-ai');
    var tabJson = document.getElementById('tab-json');
    var paneAi = document.getElementById('pane-ai');
    var paneJson = document.getElementById('pane-json');

    if (tabAi && tabJson && paneAi && paneJson) {
        tabAi.addEventListener('click', function () {
            tabAi.classList.add('on'); tabJson.classList.remove('on');
            paneAi.style.display = ''; paneJson.style.display = 'none';
        });
        tabJson.addEventListener('click', function () {
            tabJson.classList.add('on'); tabAi.classList.remove('on');
            paneJson.style.display = ''; paneAi.style.display = 'none';
        });
    }

    var form = paneAi ? paneAi.querySelector('form') : null;
    var btn = document.getElementById('parse-btn');
    var loading = document.getElementById('loading');
    if (form && btn && loading) {
        form.addEventListener('submit', function () {
            btn.disabled = true; btn.style.display = 'none'; loading.style.display = '';
        });
    }

    document.addEventListener('dragover', function (e) { e.preventDefault(); });
    document.addEventListener('drop', function (e) { e.preventDefault(); });

    function setupDropZone(zoneId, inputId, listId) {
        var zone = document.getElementById(zoneId);
        var input = document.getElementById(inputId);
        var list = document.getElementById(listId);
        if (!zone || !input) return;
        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('dragover', function (e) {
            e.preventDefault(); e.stopPropagation();
            zone.style.borderColor = 'var(--color-oshi, #C7414F)';
            zone.style.background = 'rgba(199,65,79,0.05)';
        });
        zone.addEventListener('dragleave', function () {
            zone.style.borderColor = ''; zone.style.background = '';
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault(); e.stopPropagation();
            zone.style.borderColor = ''; zone.style.background = '';
            input.files = e.dataTransfer.files;
            showFiles(list, e.dataTransfer.files);
        });
        input.addEventListener('change', function () { showFiles(list, this.files); });
        function showFiles(el, files) {
            el.textContent = '';
            for (var i = 0; i < files.length; i++) {
                var d = document.createElement('div');
                d.textContent = files[i].name;
                el.appendChild(d);
            }
        }
    }

    setupDropZone('ai-drop-zone', 'ai-image-input', 'ai-file-list');
    setupDropZone('drop-zone', 'json-file-input', 'drop-file-list');
});
</script>
</x-app-layout>
