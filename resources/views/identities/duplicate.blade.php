<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('identities.show', $fcMembership) }}" class="detail-back">‹ 複製をやめる</a>

    <div class="sec-label">名義を複製</div>
    <p style="font-size:12px; color:var(--color-ink-sub); margin-bottom:12px;">
        「{{ $fcMembership->person->name }}」の個人情報を引き継ぎ、新しい名義を作成します。
    </p>

    {{-- 引き継ぎ表示（読み取り専用） --}}
    <div class="d-block">
        <div class="d-h">個人情報（引き継ぎ）</div>
        <div class="d-row"><span class="k">氏名</span><span class="v">{{ $fcMembership->person->name }}</span></div>
        @if($fcMembership->person->birth_date)
        <div class="d-row"><span class="k">誕生日</span><span class="v mono">{{ \Carbon\Carbon::parse($fcMembership->person->birth_date)->format('Y.m.d') }}</span></div>
        @endif
        @if($fcMembership->person->phone)
        <div class="d-row"><span class="k">電話番号</span><span class="v">••••••••</span></div>
        @endif
        @if($fcMembership->person->address)
        <div class="d-row"><span class="k">住所</span><span class="v">••••••••</span></div>
        @endif
    </div>

    <form method="POST" action="{{ route('identities.store-duplicate', $fcMembership) }}" data-e2e-form>
        @csrf

        {{-- 名義の基本 --}}
        <div class="d-block">
            <div class="d-h">新しい名義の情報</div>
            <div class="f-field">
                <label for="label">名義の呼び名 <span class="opt">（任意）</span></label>
                <input class="f-input" type="text" id="label" name="label" value="{{ old('label') }}" placeholder="例：母名義・ゆきちゃん共同">
            </div>
            <div class="f-field" style="margin-bottom:0;">
                @include('partials.member-picker', ['selectedGroupMemberId' => old('group_member_id'), 'selectedColor' => old('oshi_color')])
            </div>
        </div>

        {{-- ログイン情報 --}}
        <div class="d-block">
            <div class="d-h">ログイン情報</div>
            <div class="f-field">
                <label for="member_no">会員番号</label>
                <input class="f-input" type="text" id="member_no" name="member_no" value="{{ old('member_no') }}" placeholder="会員番号">
                <input type="hidden" id="member_no_hint" name="member_no_hint" value="">
            </div>
            <div class="f-field">
                <label for="fc_password">パスワード</label>
                <input class="f-input" type="password" id="fc_password" name="fc_password" value="{{ old('fc_password') }}" placeholder="ログインパスワード">
            </div>
            <div class="f-field">
                <label for="login_id">ID <span class="opt">（任意）</span></label>
                <input class="f-input" type="text" id="login_id" name="login_id" value="{{ old('login_id') }}" placeholder="ログインIDがあれば入力">
            </div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="email">メールアドレス <span class="opt">（暗号化保存）</span></label>
                <input class="f-input @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="fc-login@example.com">
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- 入会日 --}}
        <div class="d-block">
            <div class="d-h">入会日</div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="joined_month_input">入会年月</label>
                <input class="f-input @error('joined_month_input') is-invalid @enderror" type="month" id="joined_month_input" name="joined_month_input" value="{{ old('joined_month_input') }}">
                @error('joined_month_input')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <noscript><div class="warn">この画面の利用にはJavaScriptが必要です。ブラウザの設定を確認してください。</div></noscript>
        <button type="submit" class="btn btn-primary" id="e2e-submit-btn" disabled>この名義を複製する</button>
    </form>
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function(){
    if(window.e2eUi){ document.getElementById('e2e-submit-btn').disabled = false; }
});
</script>
</x-app-layout>
