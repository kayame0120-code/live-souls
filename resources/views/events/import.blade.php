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

    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:16px;">
        公演一覧テキストを貼り付けると、AIが日付・会場・公演名に分解します。<br>
        共有マスタ（events）へ入るだけで、名義の選択はありません。
    </p>

    <form method="POST" action="{{ route('events.import.parse') }}">
        @csrf
        <label class="form-label" for="text">公演一覧テキスト</label>
        <textarea class="form-textarea @error('text') is-invalid @enderror"
                  id="text" name="text" rows="8"
                  placeholder="例:&#10;2026/09/12 横浜アリーナ Prism of Night&#10;2026/09/13 横浜アリーナ Prism of Night">{{ old('text') }}</textarea>
        @error('text')<div class="form-error">{{ $message }}</div>@enderror
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">解析する</button>
    </form>
</x-app-layout>
