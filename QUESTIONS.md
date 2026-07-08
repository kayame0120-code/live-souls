# QUESTIONS.md — 現場手帖

## 未解決・保留（安全側で仮置き中）

### QV12-1: heic 受付は libheif 未導入のため保留（安全側=拒否で仮置き）

- **発見日**: 2026-07-08（v1.2改修）
- **spec根拠**: §4「heicのデコードとEXIF除去には libheif 導入が前提（未導入では安全に保存できないため受付不可）」/ 指示書T10「libheifを導入できない環境なら QUESTIONS.md へ隔離し、安全側（heic拒否）で仮置き」
- **状況**: 実行環境（ローカル PHP 8.2.32）は **GD のみ・imagick/libheif なし**（`imagick=no` を確認）。
  GD は heic をデコードできず、**再エンコードによる EXIF 除去を保証できない**。
- **仮置き（安全側）**: 写真受付は **jpeg/png/webp のみ**。heic は拒否のまま。
  EXIF除去を素通しにして heic を通すことは禁止（指示書T10）——のためこの判断。
- **解除条件（人間の infra タスク）**: 本番/開発環境に **imagick + libheif** を導入する。
  導入後の対応: `AttendanceController::validatedData()` の `mimes` に `heic` を追加、
  `PhotoService::reencodeWithoutMetadata()` を imagick 経由の heic デコードに対応、
  `PhotoTest::test_heicアップロードは拒否される` を「heicも受付・EXIF除去」へ書き換え。
- **テスト現況**: 現環境の安全側挙動（heic拒否）をテストで担保済み（`PhotoTest`）。
  libheif導入後の受付テストは infra 導入後に有効化する。
- **deploy_runbook** に libheif 導入手順を記載済み。

---

## クローズ済みの記録（監査用）

### v1.2 移行の現物値・検証（spec §8 記録義務）
- 移行前 attendances 1件: `event_name="なにわ男子 LIVE TOUR 2026 「ND⁵」" / event_date=2026-08-15 / venue_id=1`
- events を逆生成 → 1件に集約、attendances.event_id=1 を付与。
- マイグレーション内蔵の機械検証＋`genba:verify-event-migration` で公演名・日付・会場の一致を確認（生出力はREPORT.md）。
- 旧 event_name / event_date / venue_id は検証通過後に DROP。
- バックアップ: `database/database.sqlite.v1.1-pre-v1.2.bak`（md5一致確認済み）。

### v1.1.1 で確定済み（継続有効）
- Q3 参戦・申込の削除規則（won無し削除可 / won付き削除不可）→ v1.2 spec §5 に継承・実装/テスト済み。
- Q5（v1.1のheic見送り）→ v1.2 で「libheif前提の受付」に方針変更。現環境では上記 QV12-1 の通り保留。

### v1.0/v1.1 のクローズ済み質問
- Q1 laravel-starter 未セットアップ → 解決済み。
- Q2 E1 グループ削除 → A案（配下0件時のみ削除）で確定・実装済み。
- Q4 当落結果の入力動線 → 実装済み（当落画面セレクト＋参戦詳細）。
