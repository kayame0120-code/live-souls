# deploy_runbook.md — 現場手帖 v1.0 デプロイ手順

## 前提
- Fly.io (nrt) にデプロイ済みの空 Laravel アプリが存在する
- Fly Postgres が接続済み（`DATABASE_URL` が Fly secrets に設定済み）
- feature/genba-techo-v1 ブランチが main にマージ済み

## 手順

### 1. APP_KEY の確認
```bash
fly secrets list
```
`APP_KEY` が設定されていることを確認。未設定なら:
```bash
fly secrets set APP_KEY=base64:$(openssl rand -base64 32)
```

### 2. デプロイ
```bash
fly deploy
```

### 3. マイグレーション実行
```bash
fly ssh console -C "php artisan migrate --force"
```

### 4. 初回ユーザー作成（tinker で直接作成）
```bash
fly ssh console -C "php artisan tinker --execute=\"
\\\$u = App\Models\User::create([
    'name' => '片倉',
    'email' => 'YOUR_EMAIL',
    'password' => Hash::make('YOUR_PASSWORD'),
]);
echo 'User ID: ' . \\\$u->id;
\""
```

### 5. 動作確認
```bash
# /up ヘルスチェック
curl -s -o /dev/null -w "%{http_code}" https://YOUR_APP.fly.dev/up

# ブラウザでログイン確認
# https://YOUR_APP.fly.dev/login
```

### 6. 招待コード発行
ログイン後、画面上部の「招待管理」から招待コードを発行し、友人に `/register/{code}` の URL を共有。

## 注意事項
- `fly.toml` に `release_command` でのマイグレーション自動実行は入れていない（CLAUDE.md 規約）
- マイグレーションは毎回手動で実行すること
- `.env` の変更は行っていない。本番の環境変数は Fly secrets で管理
