<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>パスワード確認 — 現場手帖</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-title">パスワード確認</div>
        <div class="auth-subtitle">機微情報を表示する前に本人確認を行います</div>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="password">ログインパスワード</label>
                <input class="form-input @error('password') is-invalid @enderror"
                       id="password" type="password" name="password" required autofocus>
                @error('password')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">確認する</button>
        </form>
    </div>
</div>
</body>
</html>
