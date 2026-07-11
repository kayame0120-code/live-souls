# deploy_runbook.md — 現場手帖 v2.1 デプロイ手順

> 人間が上から順に実行するだけの状態にしてある。

## 0. 前提
- Fly.io (nrt) + Fly Postgres 接続済み（`DATABASE_URL` 注入済み）
- feature/v2.0 を main にマージ済み（PR #1）
- ローカル検証: V1〜V3 全通過（123テスト・308アサーション）

## 1. 事前設定（Fly secrets）

### 1-1. LLM（AI一括登録用・必須）
```bash
fly secrets set LLM_DRIVER=openai OPENAI_API_KEY=sk-xxxxxxxx
```
Geminiを使う場合:
```bash
fly secrets set LLM_DRIVER=gemini GEMINI_API_KEY=xxxxxxxx
```

### 1-2. 写真ストレージ（Fly Tigris）
```bash
fly secrets set \
  PHOTO_DISK=s3 \
  AWS_ACCESS_KEY_ID=<tigris_key> AWS_SECRET_ACCESS_KEY=<tigris_secret> \
  AWS_DEFAULT_REGION=auto AWS_BUCKET=<bucket> AWS_ENDPOINT=<endpoint> \
  AWS_USE_PATH_STYLE_ENDPOINT=true
```

### 1-3. Places API（会場オートフィル・任意）
```bash
fly secrets set GOOGLE_PLACES_API_KEY=<api_key>
```

## 2. デプロイ

```bash
fly deploy
```

## 3. マイグレーション

```bash
fly ssh console -C "php artisan migrate --force"
```

v2.1の新規マイグレーション:
- idol_groups / group_members テーブル作成
- fc_memberships に group_member_id / label / renewal_dismissed_at 追加
- fc_memberships.group_id の FK を idol_groups に変更
- tour_deadlines テーブル作成（events から締切カラム移動）
- setlists を tour_id 紐づけに変更
- user_idol_groups テーブル作成（ユーザーのグループタブ管理）

## 4. シーダー（STARTOグループマスタ投入）

```bash
fly ssh console -C "php artisan db:seed --class=StartoSeeder --force"
```

## 5. 動作確認

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<app>.fly.dev/up   # → 200
```

ブラウザ確認:
- ホーム: 次の現場・更新期間カード・チケット確認
- 名義: グループタブ（＋で追加・ドラッグ並び替え）・担当メンバー選択・複製
- 公演: AI一括登録 / JSON一括登録（ドラッグ&ドロップ）
- 当落: 申込登録（単一名義＋同行者）・締切表示
- セットリスト: 手動追加・AI解析

## 6. ロールバック

```bash
fly releases
fly deploy --image <previous_image>
```
