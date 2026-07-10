# REPORT.md — 現場手帖 v2.1（2026-07-11）

> 検証はすべて **PHP 8.2.32** で実行。
> v2.1: §3 idol_groups/group_members/setlists/締切カラム + §4 名義複製・担当メンバー選択 + §5 AI一括登録LlmService移行

## 検証ライン判定表

### V1: `php artisan migrate --force` — YES
```
Nothing to migrate.
EXIT: 0
```
（全マイグレーションは人間投入済みのbatch 3で既に実行完了）

### V2: `/up` HTTP 200 — YES
```
200
```

### V3: `php artisan test` — YES（124テスト・308アサーション）
```
Tests:    124 passed (308 assertions)
Duration: 1.27s
```
v2.1新規テスト（StartoSeederTest: 7件、IdentityDuplicateTest: 4件）含め全通過。
EventImportParserTest(5件)は§6削除により減。既存テスト退行なし。

## 実装手段の決定記録

| 決定 | 根拠 |
|---|---|
| color_hexはCC裁量でMaterial Design系の代表色を採用 | spec §3「公式の精密な値である必要はない」に準拠 |
| oshi_colorバリデーションをHEX正規表現に拡張 | メンバーカラー由来のHEXも受け入れる必要あり。旧プリセット11色限定は意味を失った |
| member-pickerはidol_groups全件をJSONでプリロード | 14グループ82人のためAPI分割不要。1リクエストで完結 |
| LlmServiceの公演パースは同期呼び出し | 既存EventImportParserと同じ同期フロー維持（spec「ステートレス構造を踏襲」）。ParseEventsWithLlm jobはキュー利用時のインフラとして残置 |
| 当落締切の自動マッチは会場名部分一致＋日付完全一致 | 一致しない場合の手動選択UIあり（spec §4.6「未マッチは手動選択」） |
| EventImportParser削除 | LlmService移行完了後に削除（指示書§6「Step5動作確認後」準拠） |

## 完了状況（cc_instructions_v2.1.md 照合）

| 完了条件 | 状況 |
|---|---|
| §3.1 idol_groups/group_membersシーダー+テスト | ✅ 14グループ82人投入・テスト7件。NEWSはspec v2.4訂正表で単色 |
| §3.2 events 締切カラム活用+編集画面 | ✅ events.edit/update新設・当落カードに締切表示 |
| §3.3 setlists手動登録フォーム | ✅ show+addItem+destroyItem+bulkStore+AI一括 |
| §4 名義複製+担当メンバー選択 | ✅ member-picker・duplicate画面・テスト4件 |
| §5 LlmService移行Step4-7 | ✅ 公演/セトリ/締切の3系統AI一括登録完了 |
| §6 EventImportParser削除 | ✅ 削除済み |
| §7 Deploy1(本番投入) | ⏸ 人間の最終確認後にmainマージ→デプロイ |

## QUESTIONS.md 残件一覧

| No | 内容 | ステータス |
|---|---|---|
| QV20-2 | arena_view_keyマージ方法 | Deploy2で別ブランチ。spec §7/§9で方針確定 |

---

## v2.0 報告（2026-07-10）— 完了

V1 migrate YES / V2 /up 200 YES / V3 test 118テスト全通過。
T1更新通知・T2開場表示・T3チケット確認・T4 QV13-1確認・T5 arena_view_key・T7 LlmService基盤。
