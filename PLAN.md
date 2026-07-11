# PLAN.md — 現場手帖

## v2.7 改修（2026-07-12）

spec v2.6 / cc_instructions_v2.7.md に基づく改修。v2.1完了済み（124テスト全通過）。

### T0. 現状調査 ✅
- コード変更なし。調査結果はREPORT.mdに記載

### T1. JSONアップロード窓口の統合
- events/setlist両JSONを1つの窓口で受付。スキーマ自動判定
- 確認画面必須（直INSERT禁止）
- setlist JSON単体も統合窓口から登録可
- バリデーション強化（必須キー・型・FK参照）
- 不正JSONは日本語エラー（500禁止）

### T2. AI一括登録の改修（画像主・テキスト従 / OpenAI一本化）
- 画像アップロード主入力（最大5枚・jpeg/png/webp）、テキスト折りたたみ
- Ollama/Geminiドライバ削除、OpenAI一本
- テスト用フェイクドライバ維持
- 解析画像は永続保存しない
- 確認画面にツアー所属グループ選択

### T3. 公演タブのグループ束ね（3階層化）
- `tours.idol_group_id`（FK→idol_groups、nullable）追加マイグレーション
- 3階層UI: グループカード→ツアー一覧→公演詳細
- グループ追加UI（重複チェック付き）
- 当落タブは変更なし

### T4. 操作性の改修（スワイプ / 日本語化）
- バリデーション日本語化（APP_LOCALE=ja・全メッセージ日本語）
- メイン5画面のスワイプ切替（詳細画面では無効）

### T5. セキュリティ改修（E2E化ほか）
- 鍵階層の図・E2E経路一覧を提出
- エンベロープ方式でE2E暗号化実装
- CSP設定
- 2FA(TOTP)有効化
- password.confirm再認証
- 基準16項目の自己判定表提出

### 検証・成果物
- V1 migrate / V2 /up 200 / V3 test 全通過
- QUESTIONS.md / REPORT.md 更新

---

## 過去バージョン

### v2.1 改修（2026-07-11）— 完了
spec v2.4 / cc_instructions_v2.1.md。§3 idol_groups/group_members/setlists/締切 + §4 名義複製・担当メンバー選択 + §5 AI一括登録LlmService移行。124テスト全通過。

### v2.0 改修（2026-07-10）— 完了
spec_v2.0.md / cc_instructions_v2.0.md。T1〜T7完了。118テスト全通過。

### v1.2 改修（2026-07-08）— 完了
破壊的変更（attendances の event_id 化）。81テスト全通過。

### v1.1.1 / v1.1 / v1.0 — 完了
git 履歴参照
