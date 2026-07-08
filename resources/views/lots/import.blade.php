<x-app-layout :hide-header="true" :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('lots.index') }}" class="back">← 戻る</a>
            <h1>一括インポート</h1>
        </div>
    </x-slot:pageHeader>

    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:16px;">
        ツアーの公演一覧テキストを貼り付けると、日付・会場・公演名に分解されます。<br>
        解析結果は次の画面で確認・修正できます。
    </p>

    <form method="POST" action="{{ route('lots.import.parse') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="text">公演一覧テキスト</label>
            <textarea class="form-textarea @error('text') is-invalid @enderror"
                      id="text" name="text" rows="10"
                      placeholder="例:&#10;2026/09/12 横浜アリーナ&#10;2026/09/13 横浜アリーナ&#10;9月20日 大阪城ホール">{{ old('text') }}</textarea>
            @error('text')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">解析する</button>
    </form>
</x-app-layout>
