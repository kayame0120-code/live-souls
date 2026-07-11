<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>2段階認証 — 現場手帖</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-title">2段階認証</div>
        <div class="auth-subtitle">認証アプリに表示されているコードを入力してください</div>

        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="code">認証コード</label>
                <input class="form-input @error('code') is-invalid @enderror"
                       id="code" type="text" inputmode="numeric" autocomplete="one-time-code" name="code" autofocus>
                @error('code')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="recovery_code">またはリカバリーコード</label>
                <input class="form-input @error('recovery_code') is-invalid @enderror"
                       id="recovery_code" type="text" name="recovery_code" autocomplete="off">
                @error('recovery_code')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">認証する</button>
        </form>
    </div>
</div>
</body>
</html>
