# REPORT.md — 現場手帖 v1.1 改修レポート（2026-07-08）

> v1.0 のレポートは末尾に残置。本節が最新。
> **検証はすべて PHP 8.2.32（`/usr/bin/php8.2`・本番Flyと同一系）で実行**。
> v1.1.1（Q3/Q5 確定反映）まで含む。**QUESTIONS.md は空（未解決ゼロ）**。

## 検証ライン判定表（生出力）

### V1: `php8.2 artisan migrate --force`

```
   INFO  Nothing to migrate.

V1 EXIT: 0
```

（v1.1 マイグレーション3本の初回適用時の生出力）
```
   INFO  Running migrations.
  2026_07_08_100001_add_joined_on_and_convert_from_joined_month . 22.06ms DONE
EXIT: 0
---
   INFO  Running migrations.
  2026_07_08_100002_drop_legacy_columns_from_fc_memberships ..... 23.62ms DONE
  2026_07_08_100003_create_attendance_photos_table ............... 6.17ms DONE
EXIT: 0
```

### V2: `/up` ヘルスチェック

```
V2 HTTP: 200
```

### V3: `php8.2 artisan test`

```
  Tests:    67 passed (167 assertions)
  Duration: 0.70s

V3 EXIT: 0
PHP 8.2.32 (cli) (built: Jul  2 2026 14:12:04) (NTS)
```

## joined_month → joined_on 変換の機械検証（生出力）

`php8.2 artisan genba:verify-joined-on`（DROP前に実行）:

```
joined_month 非null件数: 1
joined_on    非null件数: 1
id=1  joined_month="2022-10"  joined_on="2022-10-01"  expected="2022-10-01"  OK
検証通過: 1件すべて一致。DROP可能です。
EXIT: 0
```

- バックアップ: `database/database.sqlite.v1.0.bak`（md5一致確認済み: `1eaf53eb55ae5b00412d72b2769470e3`）
- 変換はPHP側（クエリビルダのみ・生SQL不使用）。形式不一致は例外で中断する設計
- 変換マイグレーション自体にも件数・値の検証を内蔵（本番実行時の安全弁）

## v1.1 実装内容（指示書 §2 の11工程）

| 順 | 作業 | 状態 |
|---|---|---|
| 1 | 廃止カラム参照の除去（club_name / joined_month / renewal_cycle） | ✅ モデル・Service・Controller・全Blade |
| 2 | グループ削除ガード（「先に名義を削除または移動してください」） | ✅ + テスト2件 |
| 3 | 更新期間アクセサ + 名義詳細表示 + 受付中バッジ | ✅ + テスト7件（境界日・年跨ぎ・うるう年含む） |
| 4 | 申込登録 `/lots/create` + 当選昇格 + applied除外 | ✅ + テスト6件 |
| 5 | 座席3フィールド + seat_raw自動合成（手動優先） | ✅ + テスト3件 |
| 6 | 公演名サジェスト（メンバー横断・読み取りのみ） | ✅ `/api/events/suggest` |
| 7 | 写真添付（EXIF除去・5枚・10MB制限） | ✅ + テスト5件 |
| 8 | 見え方マッピング（会場詳細タイル・削除Policy） | ✅ |
| 9 | 一括インポート `/lots/import` | ✅ + パーサテスト6件 |
| 10 | 招待登録の同意文言 | ✅ |
| 11 | Places API オートフィル（フォールバック必須） | ✅ キー未設定/失敗時は空返却→手入力 |

mockup.html 改訂（指示書 §4）: 丸スウォッチ / 当落導線 / 会場詳細タイル画面 / 参戦登録（座席3フィールド）画面 — ✅

## 実装手段の主要決定（spec が明記しない範囲の裁量・要確認は QUESTIONS.md）

| 決定 | 理由 |
|---|---|
| ホーム「次の現場」を planned/attended のみに（applied除外） | applied は当落未確定であり「次の現場」たりえない（§5-7 の趣旨を適用） |
| ホーム「今年の参戦」から applied も除外 | 落選した申込が参戦数に混入するのを防ぐ |
| 当選昇格は status=applied のときのみ | attended 等からの「昇格」は降格を伴うため（§5-7「昇格」の文理解釈） |
| インポートの年なし日付（9/20等）は今年と解釈 | [既定] のパーサ精度は確認テーブルで担保する設計のため |
| 会場の名前完全一致は既存を再利用 | §5-10-3「既存venue部分一致→なければ新規作成」の適用・重複防止 |
| 写真の閲覧はストリーム配信（認証必須ルート） | メンバー間共有かつ公開インターネットへの露出を防ぐ |
| result更新は当落画面（セレクト即送信）+ 参戦詳細の両方 | §5-7-2 は /lots を明記。参戦詳細は v1.0 から継続 |
| HEIC 受付除外 | **QUESTIONS.md Q5**（環境上EXIF除去を保証できない・安全側） |

## テスト構成（61件・spec §7 テスト化必須の対応表）

| spec §7 必須項目 | テスト |
|---|---|
| グループ削除拒否/成功 | GroupDeleteTest（2件） |
| 他ユーザーの venue_note/attendance 直アクセス403 | MultiTenantTest（8件） |
| 他ユーザーの写真削除403・閲覧可 | PhotoTest |
| うるう年2/29の年齢 | PersonAgeTest（6件） |
| 1月入会（同年内）/ 2月入会（年跨ぎ）/ 受付境界日 | RenewalWindowTest（7件） |
| 招待コード同時多重使用 | InvitationTest（6件） |
| インポートの解析不能行（無言で捨てない） | LotImportParserTest（6件） |
| 当選昇格 / applied除外 / 自動降格しない | LotFlowTest（6件） |
| 写真 6枚目拒否 / 10MB超拒否 / EXIF除去 / **heic拒否** | PhotoTest（6件） |
| joined_month変換突合 | JoinedMonthConverterTest（3件）+ genba:verify-joined-on |
| **参戦削除規則（applied/全lost/一般参戦=可, won付き=不可, 写真実体削除）** | AttendanceDeleteTest（5件） |

## v1.1.1: Q3/Q5 確定の反映（2026-07-08）

| 項目 | 内容 |
|---|---|
| Q3 参戦・申込の削除 | won無し（applied/全pending・lost/一般参戦）は削除可・pivot/写真cascade＋ストレージ実体削除・確認ダイアログ。won付きは削除不可（skipped変更で対応）。`Attendance::canBeDeleted()` で判定 |
| Q5 HEIC | jpeg/png/webpのみ受付、heic拒否。HEIC対応はv1.2候補 |
| spec整合 | **確定連絡時に spec.md 実ファイルへ未反映（git c3dbd4fと同一）だったため、プロンプト明文の確定内容を §4/§6/§7・変更履歴(v1.1.1)へ転記**。独自判断ではなく確定事項の転記 |
| QUESTIONS.md | **全件クローズ・空**（未解決ゼロ） |

## 動作確認コマンド

```bash
# 検証は必ず PHP 8.2 で行う
/usr/bin/php8.2 artisan migrate --force        # V1
/usr/bin/php8.2 artisan test                   # V3（61件）
/usr/bin/php8.2 artisan serve                  # → http://127.0.0.1:8000

# 変換検証（本番マイグレーション時にも DROP 前に実行可能）
/usr/bin/php8.2 artisan genba:verify-joined-on
```

## QUESTIONS.md 残件

**なし。** Q1〜Q5 全件クローズ・未決ゼロ（詳細は QUESTIONS.md の監査記録）。

---

# （過去）REPORT — v1.0 実装レポート

## 検証ライン判定表

| # | 判定条件 | 結果 |
|---|---|---|
| V1 | migrate --force | YES (exit 0) |
| V2 | /up | YES (HTTP 200) |
| V3 | テスト | YES (29 passed → v1.1 で 61 passed に拡張) |

v1.0 の詳細（実装内容・セルフレビュー・セキュリティ修正）は git 履歴
`1be8709` / `cc2b737` / `d99305a` / `5cf7270` を参照。
