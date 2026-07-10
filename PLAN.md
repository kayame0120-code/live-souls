# PLAN.md — 現場手帖

## v2.0 改修（2026-07-10）

spec_v2.0.md / cc_instructions_v2.0.md に基づく改修。既存コードは変更せず、新機能追加のみ。

### すぐ着手（依存なし・指示書§2）
- **T1 ホーム:更新通知カード** — HomeService に getRenewalMemberships() 追加、home.blade に「更新期間の名義」カード表示。テスト付き
- **T2 参戦詳細:開場時間表示** — attendances/show.blade に open_time を表示追加（既存フィールド）
- **T3 ホーム:チケット確認通知** — HomeService に getTicketReminders() 追加、「確認が必要」に「チケット確認はお済みですか？」表示。テスト付き
- **T4 QV13-1 反映確認** — events.start_time 優先が全画面で守られているか調査。差分があれば修正

### 先行マイグレーション（指示書§4）
- **T5 venues.arena_view_key** — nullable string カラム追加（マイグレーションのみ・利用側は未決）

### 未決→QUESTIONS.md（指示書§3,§4）
- **T6 QUESTIONS.md更新** — spec 7章の未決5件 + 名義複製ラフ案 + Ollama Step1調査結果を記録

### Ollama基盤（指示書§5）
- **T7 Ollama Step1: 前例確認** — Flyマシンスペック・メモリ使用量の確認と報告

### 検証・成果物
- V1 migrate / V2 /up 200 / V3 test 全通過
- QUESTIONS.md / REPORT.md 更新

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
