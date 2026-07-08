<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>招待登録 — 現場手帖</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-title">現場手帖</div>
        <div class="auth-subtitle">招待登録</div>

        <form method="POST" action="{{ route('register.store', $invitation->code) }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="name">ニックネーム</label>
                <input class="form-input @error('name') is-invalid @enderror"
                       id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">メールアドレス</label>
                <input class="form-input @error('email') is-invalid @enderror"
                       id="email" type="email" name="email" value="{{ old('email') }}" required>
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
                <label class="form-label" for="password_confirmation">パスワード（確認）</label>
                <input class="form-input" id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            {{-- 写真共有の同意文言（spec §5-4-3・登録＝同意） --}}
            <p style="font-size:11px; color: #8A8C92; line-height:1.8; margin-bottom:16px;">
                投稿した参戦写真はメンバー間で共有されます。登録することでこれに同意したものとみなされます。
            </p>

            <button type="submit" class="btn btn-primary">登録する</button>
        </form>
    </div>
</div>
</body>
</html>
