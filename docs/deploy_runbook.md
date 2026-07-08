# deploy_runbook.md — 現場手帖 v1.1 デプロイ手順

> 人間が上から順に実行するだけの状態にしてある。CC の担当はローカル検証まで（CLAUDE.md デプロイ境界）。
> **v1.1 は破壊的変更（fc_memberships のカラムDROP）を含む。本番DBのバックアップを取ってから実行すること。**

## 0. 前提
- Fly.io (nrt) にデプロイ済み・Fly Postgres 接続済み（`DATABASE_URL` 注入済み）
- feature/genba-techo-v1 を main にマージ済み
- ローカル検証は PHP 8.2.32 で V1〜V3 全通過済み（REPORT.md 参照）

## 1. 事前設定（v1.1 で新規・片倉タスク）

### 1-1. 写真ストレージ（Fly Tigris / S3互換）
```bash
# Tigris バケット作成（Fly の拡張）
fly storage create

# 上記で表示される認証情報を secrets に設定
fly secrets set \
  PHOTO_DISK=s3 \
  AWS_ACCESS_KEY_ID=<tigris_key> \
  AWS_SECRET_ACCESS_KEY=<tigris_secret> \
  AWS_DEFAULT_REGION=auto \
  AWS_BUCKET=<bucket_name> \
  AWS_ENDPOINT=<tigris_endpoint> \
  AWS_USE_PATH_STYLE_ENDPOINT=true
```
- 未設定でもアプリは起動する（`PHOTO_DISK` 既定=local）が、本番の写真は Tigris 前提。
- ローカルは何も設定不要（local ディスク）。

### 1-2. Places API（会場オートフィル・任意）
```bash
# Google Cloud で Places API (New) を有効化しキー発行後
fly secrets set GOOGLE_PLACES_API_KEY=<api_key>
```
- **未設定でも会場登録は動作する**（手入力フォールバック・spec §5-11）。急がなければ後回し可。

## 2. デプロイ

### 2-1. 本番DBバックアップ（破壊的変更前・必須）
```bash
# Fly Postgres のスナップショット確認（自動日次バックアップの最新を確認）
fly postgres list
# 手動スナップショットを取る場合はボリューム経由。最低でも直近バックアップ時刻を記録しておく。
```

### 2-2. デプロイ
```bash
fly deploy
```

### 2-3. マイグレーション（順序厳守・release_command には仕込んでいない）
```bash
# (a) 変換マイグレーションまで進める前に、既存データを確認したい場合:
fly ssh console -C "php artisan tinker --execute=\"
foreach (DB::table('fc_memberships')->get(['id','club_name','joined_month','renewal_cycle']) as \\\$r) { echo json_encode(\\\$r).PHP_EOL; }
\""

# (b) マイグレーション実行（joined_on変換 → 検証 → DROP → attendance_photos）
fly ssh console -C "php artisan migrate --force"
```

**重要**: 変換マイグレーション（`..._add_joined_on_and_convert_from_joined_month`）は、
`joined_month` が `YYYY-MM` 形式でない行を検出すると**例外で停止しDROPまで進まない**（データ保全）。
停止した場合は該当値を確認し、修正してから再実行すること。

### 2-4. 変換の機械検証（DROP は同一migrateで済むが、明示確認したい場合）
本番では 2-3(b) の migrate 内で件数・値の検証が自動実行される。
別途手動確認する場合は、**DROPマイグレーション前の状態で**:
```bash
fly ssh console -C "php artisan genba:verify-joined-on"
# → "検証通過: N件すべて一致。DROP可能です。" を確認
```
※ 通常運用では migrate 一括実行で完結するため、このコマンドは調査用。

## 3. 動作確認
```bash
# /up ヘルスチェック
curl -s -o /dev/null -w "%{http_code}\n" https://<app>.fly.dev/up   # → 200

# ブラウザ確認項目（v1.1 の主要導線）
# - /login → ホーム
# - 名義詳細に「有効期限」「更新受付期間」表示・受付中バッジ
# - /lots/create で申込登録 → /lots で当選付与 → 参戦予定に昇格
# - 参戦登録で写真添付 → 会場詳細の「見え方マッピング」に表示
# - /lots/import で貼り付け → 確認テーブル → 一括登録
```

## 4. ロールバック
```bash
# アプリを直前リリースへ
fly releases
fly deploy --image <previous_image>

# DBは 2-1 のバックアップ/スナップショットから復元
# （DROPを戻す場合は down マイグレーションがあるが、DROP済みカラムのデータは復元されない点に注意）
```

## 注意事項
- `.env` / Fly secrets の値は CC が触らない（人間管理）
- `fly.toml` に release_command でのマイグレーション自動実行は入れていない（手動実行が規約）
- 検証は本番と同一系の PHP 8.2 で行う
