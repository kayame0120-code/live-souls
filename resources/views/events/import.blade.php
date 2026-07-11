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

    {{-- AI解析 --}}
    <div id="pane-ai">
        <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:12px;">
            公演一覧テキストを貼り付けると、AIが日付・会場・公演名に分解します。
        </p>
        <form method="POST" action="{{ route('events.import.parse') }}">
            @csrf
            <textarea class="form-textarea @error('text') is-invalid @enderror"
                      id="text" name="text" rows="8"
                      placeholder="公演情報のテキストをそのまま貼り付け">{{ old('text') }}</textarea>
            @error('text')<div class="form-error">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary" id="parse-btn" style="margin-top:12px;">AI解析する</button>
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
            <div class="d-h">JSON形式</div>
            <code style="font-size:10px;display:block;white-space:pre;overflow-x:auto;">{"tour":"ツアー名","events":[{"event_label":null,"event_date":"YYYY-MM-DD","start_time":"HH:MM","venue":"会場名"}]}</code>
        </div>
        <form method="POST" action="{{ route('events.import.json') }}" enctype="multipart/form-data">
            @csrf
            <div class="f-field">
                <label>JSONファイル（複数選択可）</label>
                <input class="f-input" type="file" name="json_files[]" accept=".json,.txt" multiple>
            </div>
            <div class="f-field">
                <label>または JSON文字列を貼り付け</label>
                <textarea class="form-textarea" name="json_text" rows="6" placeholder='{"tour":"...","events":[...]}'>{{ old('json_text') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">読み込む</button>
        </form>
    </div>


<script>
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
});
</script>
</x-app-layout>
