<x-app-layout :hide-fab="true" :hide-nav="true">
    <a href="{{ route('identities.index') }}" class="detail-back">‹ 名義へ戻る</a>

    <form method="POST" action="{{ route('identities.store') }}" data-e2e-form>
        @csrf

        {{-- 名義の基本 --}}
        <div class="d-block">
            <div class="d-h">名義の基本</div>
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
            <div class="f-field" style="margin-bottom:0;">
                <label for="login_id">ID <span class="opt">（任意・メアドとは別）</span></label>
                <input class="f-input" type="text" id="login_id" name="login_id" value="{{ old('login_id') }}" placeholder="ログインIDがあれば入力">
            </div>
        </div>

        {{-- 個人情報（名義人） --}}
        <div class="d-block">
            <div class="d-h">個人情報（名義人）</div>
            <div class="f-field">
                <label for="person_name">氏名</label>
                <input class="f-input @error('person_name') is-invalid @enderror" type="text" id="person_name" name="person_name" value="{{ old('person_name') }}" required>
                @error('person_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="f-field">
                <label for="address">住所</label>
                <input class="f-input" type="text" id="address" name="address" value="{{ old('address') }}">
            </div>
            <div class="f-field">
                <label for="phone">電話番号</label>
                <input class="f-input" type="tel" id="phone" name="phone" value="{{ old('phone') }}">
            </div>
            <div class="f-field">
                <label for="email">メールアドレス <span class="opt">（暗号化保存）</span></label>
                <input class="f-input @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="fc-login@example.com">
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="birth_date">誕生日</label>
                <input class="f-input @error('birth_date') is-invalid @enderror" type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}">
                @error('birth_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- 入会日 --}}
        <div class="d-block">
            <div class="d-h">入会日</div>
            <div class="f-field" style="margin-bottom:0;">
                <label for="joined_month_input">入会年月</label>
                <input class="f-input @error('joined_month_input') is-invalid @enderror" type="month" id="joined_month_input" name="joined_month_input" value="{{ old('joined_month_input') }}">
                @error('joined_month_input')<div class="form-error">{{ $message }}</div>@enderror
                <div class="f-hint">有効期限・更新受付期間はこの日付から自動計算されます。</div>
            </div>
        </div>

        <noscript><div class="warn">この画面の利用にはJavaScriptが必要です。ブラウザの設定を確認してください。</div></noscript>
        <button type="submit" class="btn btn-primary" id="e2e-submit-btn" disabled>登録する</button>
    </form>
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function(){
    if(window.e2eUi){ document.getElementById('e2e-submit-btn').disabled = false; }
});
</script>
</x-app-layout>
