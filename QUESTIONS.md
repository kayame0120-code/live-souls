# QUESTIONS.md — 現場手帖

## 未解決・保留（安全側で仮置き中）

### QV27-1: リカバリーキー紛失警告の独立UI画面

- **基準根拠**: security_criteria No.7「リカバリーキーを提示できない場合の復旧不能がUI上で明示されているか」
- **現状**: セットアップモーダル(`e2e-ui.js showRecoveryKeyScreen`)内に「このキーは今回しか表示されません。パスワードを忘れた場合、このキーがないとFC情報は二度と復元できません」テキストが存在する
- **不足**: 基準が求める水準が「モーダル内テキスト」で足りるか「独立した注意喚起画面」が必要かは判定者次第。安全側でQUESTIONSに隔離

### QV20-2: `venues.arena_view_key`のマージ方法

- **spec根拠**: spec v2.4 §7 No.1 / §9 Deploy2
- **方針確定**: arena-viewエンジン(v3)完成後に別ブランチで統合。Deploy1にはカラムのみ含める（実害なし）。
- **残**: 360度ビュータブUI（プレースホルダー）はDeploy1に含めてよいが、実データ連携はDeploy2。

### QV13-3: C（360°会場ビュー）は arena-view 資産欠落で器のみ／未着手

- **発見日**: 2026-07-09
- **状況**: arena-view一式（HTMLテンプレート＋key対応表＋seat正規化表）が未投入。
- **解除条件**: arena-view 一式を投入。

### QV12-1: heic 受付は libheif 未導入のため保留（安全側=拒否で仮置き）

- **発見日**: 2026-07-08
- **仮置き（安全側）**: 写真受付は jpeg/png/webp のみ。heic は拒否のまま。
- **解除条件**: imagick + libheif を導入する。

---

## クローズ済み（履歴）

### v2.7 クローズ（2026-07-13）

| No | 内容 | 解決 |
|---|---|---|
| QV27-3 | LLM呼び出しのキュー化 | ParseWithLlmジョブ(ShouldQueue)で実装済み |
| QV27-4 | APP_LOCALEの.env変更 | 人間が.envを変更済み。config/app.phpのデフォルトもja |
| QV13-6 | spec §3「4タブ＋公演」vs mockup「5タブ」 | 5タブで確定クローズ（片倉承認済み） |

### v2.1 クローズ

| No | 内容 | 解決 |
|---|---|---|
| QV20-1 | group_membersカラム設計 | spec v2.3/v2.4で確定。実装済み |
| QV20-3 | setlists/setlist_items設計 | spec v2.2でカラム確定。実装済み |
| QV20-4 | AI一括登録確認方式 | spec v2.2で「ステートレス」に確定。実装済み |
| QV20-5 | 名義複製UI | HANDOFF§3.2承認。実装済み |
| QV20-6 | Ollama本番投入可否 | C案確定。LlmService抽象化で実装済み |

### v1.x クローズ

| No | 内容 | 解決 |
|---|---|---|
| QV13-1 | 開演の源の二重化 | events.start_timeに一本化（片倉指示）。実装済み |
| QV13-2 | 一括インポートPHP移植 | EventImportParser実装→LlmService移行→削除済み |
| QV13-4 | spec.md v1.3未更新 | spec.md配置済み |
| QV13-5 | start_time cast問題 | アクセサ方式に変更。実装済み |
| QV14-1 | 当落入力欄 | pending自動生成で是正不要。テスト担保済み |
| Q1〜Q5 | v1.0/v1.1の各質問 | 全件解決済み（詳細は過去REPORT参照） |
