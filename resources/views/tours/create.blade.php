<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('events.index') }}" class="detail-back">‹ 公演一覧へ戻る</a>

    <div class="sec-label">ツアーを追加</div>
    <p style="font-size:12px; color:var(--color-ink-sub); line-height:1.8; margin-bottom:14px;">
        ツアー名だけ先に作ります。作成後、続けて日程（会場・日付・開演）を追加できます。<br>
        単発ライブも「1公演だけのツアー」として登録してください。
    </p>

    <form method="POST" action="{{ route('tours.store') }}">
        @csrf
        <div class="f-field">
            <label for="name">ツアー名</label>
            <input class="f-input @error('name') is-invalid @enderror" type="text" id="name" name="name"
                   value="{{ old('name') }}" required placeholder="例：LUMIÈRE LIVE TOUR 2026「Prism of Night」">
            @error('name')<div class="form-error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">作成して日程追加へ</button>
    </form>
</x-app-layout>
