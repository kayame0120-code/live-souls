# PLAN.md — 現場手帖

## v2.1 改修（2026-07-11）

spec v2.4 / cc_instructions_v2.1.md に基づく改修。v2.0完了済み・マイグレーション/モデル骨格は投入済み。

### §3.1 idol_groups / group_members シーダー
- **T1** StartoSeeder 実装（docs/member-colors_STARTO_v0.1.md → idol_groups + group_members）
  - 色名→HEX変換（CC裁量）、NEWSはspec v2.4 §3.2 訂正表（単色）を使用
  - テスト: 全グループ投入確認 + NEWS単色確認

### §3.2 events 締切カラム活用
- **T2** 公演編集画面（events.edit）新設 — 締切/発表日の手入力・更新経路
  - 当落カード（_lot-card）に締切・発表日を表示

### §3.3 setlists 手動登録
- **T3** セットリスト画面の仕上げ（並べ替え・tours/show からのセトリ導線確認）

### §4 名義複製 + 担当メンバー選択
- **T4** 担当メンバー選択UI（partials/member-picker）— idol_group→member chips→色自動反映→手動上書き
  - create/edit/duplicate フォームに統合
- **T5** 名義複製（identities.duplicate）— person引き継ぎ・FC固有のみ入力
  - テスト: persons重複なし・group_member_id/oshi_color紐づき

### §5 AI一括登録（LlmService移行）
- **T6** LlmService に parseDeadlines() 追加
- **T7** 公演AI一括登録（EventImportParser→LlmService置換）— 確認画面はステートレス維持
- **T8** EventImportParser / EventImportParserTest 削除（T7動作確認後）
- **T9** セットリストAI一括登録 — 公演詳細→セトリ画面「セトリを貼って一括登録」
- **T10** 当落締切AI一括登録 — 会場+日付でevents自動マッチ・手動選択フォールバック

### 検証・成果物
- V1 migrate / V2 /up 200 / V3 test 全通過
- QUESTIONS.md / REPORT.md 更新

---

## v2.0 改修（2026-07-10）— 完了

spec_v2.0.md / cc_instructions_v2.0.md に基づく改修。既存コードは変更せず、新機能追加のみ。
- T1〜T7 完了。118テスト全通過。LlmService基盤(Step1-3)完了

---

## v1.2 改修（2026-07-08）— 完了

破壊的変更（attendances の event_id 化）を含む改修。migration-first で実施。

- **T1-T2 events新設 + event_id移行** ✅
  events共有マスタ（user_idなし）/ add event_id → backfill（逆生成・機械検証内蔵）→ 旧3カラムDROP /
  `genba:verify-event-migration` / Attendance に event() + venue/event_name/event_date アクセサ + 日付スコープ
- **T3 email** ✅ encrypted カラム・伏字コピー
- **T4 担当色プリセット11色** ✅ `config/oshi_colors.php` + `Rule::in` + 丸スウォッチ選択
- **T5 events CRUD・検索付きセレクト・重複警告** ✅ EventController/EventService（confirm_duplicateで続行）
- **T6 一括インポート→events移設** ✅ `/events/import`（名義選択なし・全ユーザー可）
- **T7 参戦登録の作り替え** ✅ 公演セレクト→日付会場自動表示・手入力は座席と写真
- **T8 参戦した？確認** ✅ ホーム確認UI + confirmAttendance（自動遷移なし）
- **T9 当選率撤去** ✅ winRate系削除・WinRateTest削除・当落一覧に置換
- **T10 heic** ⏸ libheif未導入のため拒否のまま仮置き（QUESTIONS.md QV12-1）

### 検証・成果物 ✅
- V1 migrate:fresh / V2 /up 200 / V3 test 81件（PHP 8.2.32・生出力 REPORT.md）
- event移行の突合を genba:verify-event-migration とテストで機械担保
- REPORT.md / QUESTIONS.md / deploy_runbook.md 更新

## 残件（人間 infra タスク）
- QV12-1: heic受付（imagick+libheif 導入で解除・deploy_runbook 1-3 に手順）

## 過去
- v1.1.1 / v1.1 / v1.0 は git 履歴参照（47be217 / c3dbd4f / 1be8709 等）
