# REPORT.md — 現場手帖 v2.0（2026-07-10）

> 検証はすべて **PHP 8.2.32** で実行。
> v2.0: ホーム更新通知・チケット確認・開場時間表示・arena_view_keyマイグレーション。

## 検証ライン判定表

### V1: `php artisan migrate --force` — YES
```
2026_07_10_000001_add_arena_view_key_to_venues ................. 8.39ms DONE
EXIT: 0
```

### V2: `/up` HTTP 200 — YES
```
200
```

### V3: `php artisan test` — YES（118テスト・333アサーション）
```
Tests:    118 passed (333 assertions)
Duration: 1.27s
```
v2.0 新規テスト（HomeV20Test: 6件）含め全通過。既存テスト退行なし。

## 実装手段の決定記録

| 決定 | 根拠 |
|---|---|
| チケット確認の閾値=7日 | spec「公演日が近づいた」の具体閾値未指定。CC裁量で7日を採用（変更は`HomeService::getTicketReminders()`の`addDays(7)`のみ） |
| 更新通知はメモリ上フィルタ | `isInRenewalWindow()`がPHP側計算のため、全名義をloadしてfilter。名義数が少ないアプリ前提で妥当 |
| 開場時間はCarbon::parseで表示 | `attendances.open_time`はtime列・castなし。表示時のみCarbon化してH:i形式に |
| `arena_view_key`のみ先行マイグレーション | 指示書§4「カラム追加は先行してよい」に準拠。利用側（360度ビュータブ）は未決のため未実装 |

## 完了状況（cc_instructions_v2.0.md §7 照合）

| 完了条件 | 状況 |
|---|---|
| §2の4タスク実装・テスト済み | ✅ T1更新通知・T2開場表示・T3チケット確認・T4 QV13-1確認（変更不要） |
| §3ラフ案の提示 | ✅ QUESTIONS.md QV20-5にラフ案記載。片倉の承認待ち |
| §4未決事項のQUESTIONS.md反映 | ✅ QV20-1〜QV20-6の6件を記録 |
| §5 Step3（合意）到達 | ✅ QV20-6に調査結果・選択肢を記載。片倉の合意待ち |
| §6削除は§5完了後まで保留 | ✅ EventImportParser未削除（Ollamaパイプライン未実装のため） |

## QUESTIONS.md 残件一覧

| No | 内容 | ステータス |
|---|---|---|
| QV20-1 | 担当メンバーマスタ設計・oshi_colorとの関係 | 片倉判断待ち |
| QV20-2 | arena_view_keyマージ方法 | 片倉判断待ち（カラム追加済み） |
| QV20-3 | setlists/setlist_items設計 | 設計フェーズ待ち |
| QV20-4 | AI一括登録テーブル設計 | 設計フェーズ待ち |
| QV20-5 | 名義複製UI | ラフ案提示済み・片倉承認待ち |
| QV20-6 | Ollama本番投入可否 | 調査結果報告済み・片倉方針選定待ち |
