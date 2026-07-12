<x-app-layout :hide-fab="true" :hide-nav="true">
    <x-slot:pageHeader>
        <div class="page-header">
            <a href="{{ route('home') }}" class="back">← 戻る</a>
            <h1>セキュリティ設定</h1>
        </div>
    </x-slot:pageHeader>

    @if(session('status') === 'two-factor-authentication-enabled')
    <div class="alert alert-success">認証アプリでQRコードを読み取り、表示されたコードで有効化を完了してください。</div>
    @elseif(session('status') === 'two-factor-authentication-confirmed')
    <div class="alert alert-success">2段階認証を有効化しました。リカバリーコードを保管してください。</div>
    @elseif(session('status') === 'two-factor-authentication-disabled')
    <div class="alert alert-success">2段階認証を無効化しました。</div>
    @endif

    <div class="d-block">
        <div class="d-h">2段階認証（TOTP）</div>

        @if($state === 'disabled')
        <p style="font-size:12px;color:var(--color-ink-sub);line-height:1.8;margin-bottom:12px;">
            ログイン時にパスワードに加えて、認証アプリ（Google Authenticator等）の
            6桁コードを要求します。FC情報を守る追加の鍵になります。
        </p>
        <form method="POST" action="{{ url('user/two-factor-authentication') }}">
            @csrf
            <button type="submit" class="btn btn-primary">2段階認証を有効にする</button>
        </form>

        @elseif($state === 'pending')
        <p style="font-size:12px;color:var(--color-ink-sub);line-height:1.8;margin-bottom:12px;">
            認証アプリでこのQRコードを読み取り、表示された6桁コードを入力してください。
        </p>
        <div style="display:flex;justify-content:center;padding:12px;background:#fff;border-radius:10px;margin-bottom:12px;">
            {!! $qrCodeSvg !!}
        </div>
        <form method="POST" action="{{ url('user/confirmed-two-factor-authentication') }}">
            @csrf
            <div class="f-field">
                <label for="code">認証コード（6桁）</label>
                <input class="f-input @error('code', 'confirmTwoFactorAuthentication') is-invalid @enderror"
                       id="code" type="text" inputmode="numeric" autocomplete="one-time-code" name="code" required autofocus>
                @error('code', 'confirmTwoFactorAuthentication')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">有効化を完了する</button>
        </form>
        <form method="POST" action="{{ url('user/two-factor-authentication') }}" style="margin-top:8px;">
            @csrf @method('DELETE')
            <button type="submit" class="copy-btn" style="font-size:11px;">やり直す（QRを破棄）</button>
        </form>

        @else
        <p style="font-size:12px;color:#43A047;font-weight:600;margin-bottom:12px;">✓ 2段階認証は有効です</p>

        <div class="d-h" style="margin-top:8px;">リカバリーコード</div>
        <p style="font-size:11px;color:var(--color-ink-sub);line-height:1.7;margin-bottom:8px;">
            認証アプリが使えなくなった場合のログインに使います。安全な場所に保管してください（各コード1回のみ使用可）。
        </p>
        <div style="background:#f5f5f5;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:12px;line-height:2;margin-bottom:12px;">
            @foreach($recoveryCodes as $code)
            <div>{{ $code }}</div>
            @endforeach
        </div>

        <form method="POST" action="{{ url('user/two-factor-recovery-codes') }}" style="margin-bottom:8px;">
            @csrf
            <button type="submit" class="copy-btn" style="font-size:11px;">リカバリーコードを再生成</button>
        </form>
        <form method="POST" action="{{ url('user/two-factor-authentication') }}"
              onsubmit="return confirm('2段階認証を無効化しますか？')">
            @csrf @method('DELETE')
            <button type="submit" class="f-danger">2段階認証を無効化する</button>
        </form>
        @endif
    </div>
</x-app-layout>
