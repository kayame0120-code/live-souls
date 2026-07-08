# REPORT.md — 現場手帖 v1.2 改修レポート（2026-07-08）

> 検証はすべて **PHP 8.2.32（`/usr/bin/php8.2`・本番Flyと同一系）** で実行。
> v1.1 以前のレポートは本節の後ろに残置。

## v1.2 検証ライン判定表（生出力）

### V1: `php8.2 artisan migrate:fresh --force`
```
V1 EXIT: 0
```
（events作成→event_id backfill→旧3カラムDROP→email追加まで全migration DONE）

### V2: `/up` ヘルスチェック
```
V2 HTTP: 200
```

### V3: `php8.2 artisan test`
```
  Tests:    81 passed (215 assertions)
  Duration: 0.76s
V3 EXIT: 0
```

## event_id 移行の機械検証（生出力・DROP前・実データ1件）

`genba:verify-event-migration`（バックアップ復元→backfillまで実行→DROP前で検証）:
```
参戦総数: 1
event_id 未設定: 0
id=1  name[OK]  date[2026-08-15→2026-08-15]  venue[1→1]  OK
events 生成数: 1
検証通過: 1件すべて一致。旧3カラムのDROP可能です。
VERIFY EXIT: 0
```
- バックアップ: `database/database.sqlite.v1.1-pre-v1.2.bak`（md5一致確認済み）。
- 変換は生SQL不使用（クエリビルダ＋PHP）。backfillマイグレーション内にも件数・値の検証を内蔵し、不一致なら例外で停止（本番安全弁）。
- DROP は検証を挟むため別マイグレーションに分離（`genba:verify-event-migration` は runbook で backfill と DROP の間に実行）。

## v1.2 実装サマリ（指示書 T1〜T10）

| T | 内容 | 実装 |
|---|---|---|
| T1 | events 共有マスタ新設（user_idなし・削除ガード） | `Event` model / migration / `Event::canBeDeleted()` |
| T2 | attendances の event_id 化（破壊的） | 3migration（add+backfill+検証内蔵 / drop）＋ `genba:verify-event-migration` ＋ Attendance にevent()・venue/event_name/event_dateアクセサ・日付スコープ |
| T3 | fc_memberships.email（encrypted） | migration / `email`=encryptedキャスト / フォーム・詳細に伏字コピー |
| T4 | 担当色プリセット11色 | `config/oshi_colors.php` / `Rule::in` 検証 / 丸スウォッチ選択partial（type=color廃止） |
| T5 | events CRUD・検索付きセレクト・重複警告 | `EventController`/`EventService`（同一会場×同一日付は警告のみ・confirm_duplicateで続行） |
| T6 | 一括インポートを events へ移設 | `/events/import`（名義選択なし・全ユーザー可）。旧 `/lots/import` 廃止 |
| T7 | 参戦登録の作り替え | 公演セレクト（event_id）→日付・会場自動表示・手入力は座席と写真のみ |
| T8 | 公演日経過→「参戦した？」確認 | ホーム確認UI ＋ `HomeController::confirmAttendance`（自動遷移なし・確定でattended/skipped） |
| T9 | 当選率の撤去 | `winRate/winCount/applicationCount` 削除・`WinRateTest` 削除・名義詳細を当落一覧に |
| T10 | heic 受付（安全弁つき） | **libheif未導入のため heic拒否のまま仮置き（QUESTIONS.md QV12-1）** |

mockup.html は色プリセット・公演セレクト・参戦確認・email欄・見え方タイル等をユーザーが改訂済み（本改修と整合）。

## マルチテナント（指示書§1・退行なし）
- 他人データは **404**（UserScope で不可視・ルートモデルバインディングが404）。写真の削除/編集のみ **403**（Policy）。
- events は user_id を持たない共有マスタ（例外②）。写真閲覧（例外③）・events参照（例外④）は読取のみ。
- テスト: `MultiTenantTest`（8件・404）/ `PhotoTest`（削除403）/ `EventMasterTest`（別ユーザー追加可）/ `AttendanceConfirmTest`（他人の確認404）。

## テスト構成（81件・§9 テスト化必須の対応）
| 必須項目 | テスト |
|---|---|
| event_id移行の突合（公演名・日付・会場一致・event共有） | EventMigrationTest（3）＋ genba:verify-event-migration |
| events削除ガード（参戦0件のみ削除） | EventMasterTest |
| 公演重複警告（出るがブロックしない） | EventMasterTest |
| 参戦登録で event_id 保存 / 手入力は座席・写真のみ | LotFlowTest / PageRenderSmokeTest |
| 参戦確認（planned経過→確定でattended・自動遷移しない） | AttendanceConfirmTest（5） |
| email（encrypted保存・伏字・コピー） | IdentityV12Test |
| oshi_color（プリセット外を弾く・hex保存） | IdentityV12Test |
| 当選率非表示・当落一覧は残す | IdentityV12Test |
| 他人データ404 / 写真削除403 | MultiTenantTest / PhotoTest |
| 削除規則（applied・全lost可 / won不可・写真実体削除） | AttendanceDeleteTest（5） |
| 当選昇格 / applied除外 / 非降格 | LotFlowTest（6） |
| 更新期間の境界（1月/2月年跨ぎ/うるう年/受付境界日） | RenewalWindowTest（7・退行なし） |
| 招待二度使えない / うるう年年齢 / EXIF除去 / heic拒否 | InvitationTest / PersonAgeTest / PhotoTest |
| 全画面が200描画 | PageRenderSmokeTest（12） |

## 実装手段の主要決定
| 決定 | 理由 |
|---|---|
| Attendance に venue/event_name/event_date の**読取アクセサ**＋日付**スコープ**を追加 | event_id 化後もビュー・クエリを最小改修で維持（日付並び替えは events 相関サブクエリでDB方言非依存） |
| events の重複は `confirm_duplicate` フラグで2段階（警告→続行） | 昼夜2公演を許容しつつ誤登録を抑止（ブロックしない） |
| 参戦の会場履歴は event 経由の whereHas で解決 | attendances が venue_id を持たなくなったため |
| heic 拒否を維持 | **QUESTIONS.md QV12-1**（libheif未導入でEXIF除去を保証できない・安全側・指示書T10準拠） |

## 動作確認コマンド
```bash
/usr/bin/php8.2 artisan migrate:fresh --force   # V1
/usr/bin/php8.2 artisan test                    # V3（81件）
/usr/bin/php8.2 artisan serve                   # → /login（seederで k.ayame0120@gmail.com / kjna0809）

# 本番移行時の検証（backfill と DROP の間で実行）
/usr/bin/php8.2 artisan genba:verify-event-migration
```

## QUESTIONS.md 残件
| No | 内容 | ステータス |
|---|---|---|
| QV12-1 | heic受付は libheif 未導入のため保留（安全側=拒否で仮置き） | 保留（infra導入で解除） |

---

# （過去）現場手帖 v1.1 改修レポート（2026-07-08）

> v1.0 のレポートは末尾に残置。
> **検証はすべて PHP 8.2.32（`/usr/bin/php8.2`・本番Flyと同一系）で実行**。
> v1.1.1（Q3/Q5 確定反映）まで含む。

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
