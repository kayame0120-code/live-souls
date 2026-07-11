# deploy_runbook.md — 現場手帖 v1.2 デプロイ手順

> 人間が上から順に実行するだけの状態にしてある。CC の担当はローカル検証まで（CLAUDE.md デプロイ境界）。
> **v1.2 は破壊的変更（attendances の event_id 化＝旧 event_name/event_date/venue_id を DROP）を含む。
> 本番DBのバックアップを取り、移行検証が通ってから DROP すること。**

## 0. 前提
- Fly.io (nrt) + Fly Postgres 接続済み（`DATABASE_URL` 注入済み）
- feature/v1.2 を main にマージ済み
- ローカル検証は PHP 8.2.32 で V1〜V3 全通過済み（REPORT.md 参照）

## 1. 事前設定（片倉タスク）

### 1-1. 写真ストレージ（Fly Tigris / S3互換）※v1.1から継続
```bash
fly storage create
fly secrets set \
  PHOTO_DISK=s3 \
  AWS_ACCESS_KEY_ID=<tigris_key> AWS_SECRET_ACCESS_KEY=<tigris_secret> \
  AWS_DEFAULT_REGION=auto AWS_BUCKET=<bucket> AWS_ENDPOINT=<endpoint> \
  AWS_USE_PATH_STYLE_ENDPOINT=true
```
未設定でも起動する（既定=local）。本番写真は Tigris 前提。

### 1-2. Places API（会場オートフィル・任意）
```bash
fly secrets set GOOGLE_PLACES_API_KEY=<api_key>
```
未設定でも会場登録は動く（手入力フォールバック）。

### 1-3. （任意・heic を受け付けたい場合のみ）libheif 導入
- 現状 heic は**拒否**（QUESTIONS.md QV12-1・EXIF除去を保証できないため安全側）。
- heic を受け付けたい場合は **imagick + libheif** をイメージに追加する。
  Dockerfile に例（Debian系）:
  ```dockerfile
  RUN apt-get update && apt-get install -y libheif1 libheif-dev php-imagick && rm -rf /var/lib/apt/lists/*
  ```
  導入後、コード側の対応（mimesにheic追加・PhotoServiceのimagick切替・テスト書換）が別途必要。
  **未導入のままでよいなら本手順はスキップ**（アプリは正常動作する）。

## 2. デプロイと破壊的マイグレーション

### 2-1. 本番DBバックアップ（必須・DROP前）
```bash
fly postgres list
# 直近スナップショット時刻を記録。必要なら手動スナップショットを取得。
```

### 2-2. デプロイ
```bash
fly deploy
```

### 2-3. マイグレーション（順序厳守・release_command には仕込んでいない）

v1.2 の migration は「events作成 → event_id backfill＋検証内蔵 → 旧3カラムDROP → email追加」。
backfill と DROP の間に検証コマンドを挟めるよう、**まず DROP 直前まで**を確認したい場合は
段階実行を推奨（下記(a)）。一括で問題なければ(b)。

**(a) 段階実行（推奨・安全）**
```bash
# events作成 + event_id backfill + email追加まで（DROPマイグレーションはまだ pending でも
# 一括migrateは全pendingを流すため、DROPの手前で止めたい場合は下記コマンドで検証を先に確認）
fly ssh console -C "php artisan migrate --force"   # 全migration適用（backfill内で機械検証・不一致なら例外停止）

# 移行の突合を明示確認（DROPは同一migrateで済むが、値の一致をログで残す）
fly ssh console -C "php artisan genba:verify-event-migration"
# → "検証通過: N件すべて一致。旧3カラムのDROP可能です。" が出れば移行成功
```
> 注: backfillマイグレーションは検証を**内蔵**しており、公演名・日付・会場の不一致や
> event_id未設定があれば**例外で停止し DROP まで進まない**（データ保全）。
> `genba:verify-event-migration` は監査ログ用（DROP後は旧カラムが無いため実行不可）。

**(b) 一括実行**
```bash
fly ssh console -C "php artisan migrate --force"
```

### 2-4. 移行が停止した場合
- backfillマイグレーションが例外停止 → 旧カラムは残存（DROP未実行）。
  ログの不一致内容を確認し、データを是正してから再実行。QUESTIONS.md へ記録。

## 3. 動作確認
```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<app>.fly.dev/up   # → 200

# ブラウザ確認（v1.2の主要導線）
# - 参戦登録: 公演を検索付きセレクトで選ぶ→日付・会場が自動表示・手入力は座席と写真
# - 公演一覧 /events: 追加・一括インポート（名義選択なし）・参戦0件のみ削除
# - 公演登録: 同一会場×同一日付で重複警告（続行は可能）
# - 名義詳細: email が伏字＋コピー / 当落一覧（当選率は無い）
# - 担当色: プリセット11色の丸スウォッチ選択
# - ホーム: 公演日を過ぎた予定に「参戦した？」（自動遷移しない）
```

## 4. ロールバック
```bash
fly releases
fly deploy --image <previous_image>
# DBは 2-1 のバックアップ/スナップショットから復元
# （DROP済みカラムのデータは down マイグレーションでは復元されない点に注意）
```

## 注意事項
- `.env` / Fly secrets は CC が触らない（人間管理）
- `fly.toml` に release_command でのマイグレーション自動実行は入れない（手動実行が規約）
- 検証は本番と同一系の PHP 8.2 で行う
- 初回オーナーは seeder（`k.ayame0120@gmail.com`）で作成可: `fly ssh console -C "php artisan db:seed --force"`
