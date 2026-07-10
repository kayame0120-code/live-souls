<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('lots.index') }}" class="detail-back">‹ 当落一覧へ戻る</a>

    <div class="sec-label">締切を貼って一括登録</div>

    @if(session('error'))
    <div class="warn">{{ session('error') }}</div>
    @endif

    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:16px;">
        申込締切・当落発表日のテキストを貼り付けると、AIが会場・日付・締切に分解します。<br>
        既存の公演に自動マッチし、マッチしない場合は手動で選択できます。
    </p>

    <form method="POST" action="{{ route('deadlines.parse') }}">
        @csrf
        <textarea class="f-input @error('text') is-invalid @enderror"
                  name="text" rows="8"
                  placeholder="例:&#10;京セラドーム大阪 8/15 締切 7/20 23:59 発表 7/28&#10;東京ドーム 8/22 発表予定 7/15">{{ old('text') }}</textarea>
        @error('text')<div class="form-error">{{ $message }}</div>@enderror
        <button type="submit" class="btn btn-primary" style="margin-top:18px;">AIで解析する</button>
    </form>
</x-app-layout>
