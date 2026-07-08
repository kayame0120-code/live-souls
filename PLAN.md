# PLAN.md — 現場手帖

## v1.1 改修（2026-07-08）— 完了

### Phase A: PHP 8.2 依存ツリー修正 ✅
- config.platform.php=8.2.32 固定 / fortify ~1.36.2（passkeys非内包）
- config/fortify.php から passkeys・2FA limiter 除去
- V1〜V3 を PHP 8.2.32 で通過（commit 5cf7270）

### Phase B: DB移行 ✅
- バックアップ database.sqlite.v1.0.bak（md5一致確認）
- joined_on 変換マイグレーション（PHP側変換・形式不一致で停止する安全弁）
- genba:verify-joined-on で機械検証（1件一致）
- club_name / joined_month / renewal_cycle を DROP
- attendance_photos 作成（グローバルuser_idスコープなし）

### Phase C: 機能改修11工程 ✅
1. 廃止カラム参照除去 / 2. グループ削除ガード / 3. 更新期間アクセサ+バッジ /
4. 申込登録+当選昇格+applied除外 / 5. 座席3フィールド+seat_raw合成 /
6. 公演名サジェスト / 7. 写真添付+EXIF除去 / 8. 見え方マッピング /
9. 一括インポート / 10. 同意文言 / 11. Places APIオートフィル

### Phase D: mockup.html 改訂 ✅
- 丸スウォッチ / 当落導線 / 会場詳細タイル画面 / 参戦登録座席3フィールド画面

### Phase E: テスト ✅ 61件通過
- 更新期間境界（1月/2月年跨ぎ/境界日/うるう年）/ グループ削除ガード /
  applied除外 / 当選昇格・非降格 / 写真403・枚数・サイズ・EXIF / 変換突合

### Phase F: 検証+成果物 ✅
- V1〜V3 を PHP 8.2.32 で全通過（生出力を REPORT.md に貼付）
- REPORT.md / QUESTIONS.md / deploy_runbook.md 更新

### Phase G: Q3/Q5 確定反映（v1.1.1）✅
- Q3 削除規則: `Attendance::canBeDeleted()`（won無しは削除可/won付き不可）+ 写真実体削除 + AttendanceDeleteTest（5件）
- Q5 HEIC拒否: jpeg/png/webpのみ + PhotoTest heicテスト
- spec.md §4/§6/§7・変更履歴を確定内容へ転記（実ファイル未反映だったため）
- QUESTIONS.md 全件クローズ・空。テスト67件通過

## 残件
**なし。** 未決ゼロ・QUESTIONS.md 空。

## v1.0（初版）— 完了
Fortify招待制 / 全テーブル / 全画面 / マルチテナント / セキュリティ修正
（commit 1be8709 / cc2b737 / d99305a）
