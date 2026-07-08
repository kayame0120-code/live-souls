# QUESTIONS.md — 現場手帖

## 未解決の質問・矛盾・スキップ

**なし。**（2026-07-08 時点で全件クローズ済み）

---

## クローズ済みの記録（監査用）

### Q1: laravel-starter 未セットアップ → 解決済み（2026-07-08）
starter化を CC が実施（Fortify招待制）。

### Q2: E1 グループ削除時の配下名義の扱い → 解決済み（v1.1）
spec §7/§8 で A案（配下名義ありは削除拒否）[確定]。実装・テスト済み。

### Q4: 当落結果の入力動線 → 解決済み（v1.1）
spec §5-7-2 で /lots からの result 更新が[確定]。当落画面・参戦詳細に実装。

### Q3: 参戦・申込の削除規則 → 解決済み（2026-07-08・折衷案で確定）
片倉確定。spec §7 に[確定]・テスト化必須で追記済み。
- status=applied / 全pivot pending・lost（won 0件）/ 一般参戦 → 削除可（pivot・写真cascade＋ストレージ実体も削除・確認ダイアログ）
- won付き（昇格済み）→ 削除不可。skipped変更で「行かなかった」を表現
- 実装: `Attendance::canBeDeleted()` / `AttendanceController@destroy` / 参戦詳細ビュー
- テスト: `AttendanceDeleteTest`（5件）

### Q5: HEIC 受付 → 解決済み（2026-07-08・A案=見送りで確定）
片倉確定。写真は jpeg/png/webp のみ受付、heic は拒否。imagick+libheif によるHEIC対応はv1.2候補。
spec §4/§6/§7 を heic 除外に更新済み。
- 実装: `AttendanceController::validatedData()` の `mimes:jpeg,jpg,png,webp`
- テスト: `PhotoTest::test_heicアップロードは拒否される`

### 破壊的DROP前の現物値（spec §10-1 記録義務・監査用）
fc_memberships 全1件（id=1）: club_name=null / renewal_cycle=null /
joined_month="2022-10" → joined_on="2022-10-01"（genba:verify-joined-on で機械検証済み）。

### 参考: spec.md 実ファイル未反映の是正（2026-07-08）
Q3/Q5 確定の連絡時、spec.md 実ファイルに更新が反映されていなかった（git上c3dbd4fと同一）。
プロンプト本文に確定内容が明文で記載されていたため、確定テキストを spec.md §4/§6/§7 と
変更履歴（v1.1.1）に転記して整合させた。独自判断ではなく確定事項の転記。
