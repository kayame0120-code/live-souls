<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン — 現場手帖</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-title">現場手帖</div>
        <div class="auth-subtitle">GENBA TECHO</div>

        @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="email">メールアドレス</label>
                <input class="form-input @error('email') is-invalid @enderror"
                       id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="password">パスワード</label>
                <input class="form-input @error('password') is-invalid @enderror"
                       id="password" type="password" name="password" required>
                @error('password')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="remember"> ログイン状態を保持
                </label>
            </div>

            <button type="submit" class="btn btn-primary">ログイン</button>
        </form>
    </div>
</div>
</body>
</html>
