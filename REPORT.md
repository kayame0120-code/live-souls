# REPORT.md — 現場手帖 v1.3 view 再テンプレート（2026-07-09）

> 検証はすべて **PHP 8.2.32（`/usr/bin/php8.2`・本番Flyと同一系）** で実行。
> 本タスクは **view の骨格と CSS を mockup(v1.3) に追従させる再テンプレートのみ**（ロジック・ルート・データ設計は不変）。
> A/B（events.start_time・一括インポートPHP移植）はベースラインとして取り込み済み。C（360°）は対象外。

## 検証ライン判定表（生出力）

### V1: `php artisan migrate`
```
   INFO  Nothing to migrate.
V1 EXIT: 0
```
（本タスクで新規マイグレーションは追加していない）

### build: `npm run build`（前回のビルド忘れ事故防止のため必須）
```
public/build/assets/app-DOE0EqIZ.css  79.78 kB │ gzip: 15.78 kB
public/build/assets/app-CIomGrQN.js   46.16 kB │ gzip: 17.79 kB
✓ built in 654ms
BUILD EXIT: 0
```
新クラスの `public/build` 反映を grep で確認: ev-lead/ev-date/ev-body/ev-time/ev-count/d-block/att-hero/confirm-lead/confirm-title/btn-attended/btn-skipped/apply-row/lot-select/f-danger/detail-topbar/venue-view-btn = **全て OK**。

### V3: `php artisan test`
```
  Tests:    101 passed (282 assertions)
  Duration: 0.95s
V3 EXIT: 0
```
指定テストの個別確認（生出力抜粋）:
```
PASS Tests\Unit\EventImportParserTest          （t1 SAMPLE一致・昼夜別・ノイズ遮断・未解析保持 ほか）
PASS Tests\Feature\EventDuplicateKeyTest        （昼夜別レコード・開演違いは非重複・null同士のみ重複）
PASS Tests\Feature\EventMasterTest              （削除ガード・重複警告非ブロック・全ユーザー追加・一括インポート）
PASS Tests\Feature\PageRenderSmokeTest          （全12画面200＋名義編集/会場詳細200。公演一覧・参戦詳細・ホーム含む）
Tests: 25 passed (86 assertions)
```

## 変更したクラス／画面の対応表

| 画面 / ファイル | 変更 | 主なクラス |
|---|---|---|
| `components/app-layout.blade.php` | ボトムナビを**5タブ化**（公演=`events.index`を5つ目）。`.bottom-nav` を `repeat(5,1fr)` | bottom-nav |
| `home.blade.php` | 前任の「公演マスタ」link-row を**撤去**（導線は5タブ目へ一本化）。確認カードを mockup 構造へ改名 | confirm-card / confirm-lead / confirm-title / confirm-sub / btn-attended / btn-skipped |
| `events/index.blade.php` ＋ `_ev.blade.php` | `.ev-card` 旧構造を廃し **ev-lead → ev-actions → 今後/過去2セクション → .ev** に刷新。`EventController@index` を upcoming/past 分割＋`withCount(attendances)`（全メンバー分） | ev-lead / ev-actions / ev-new / ev-import / ev / ev-date / ev-body / ev-name / ev-venue / ev-time / ev-count / ev-delete |
| `attendances/show.blade.php` | `.detail-section` 系 → **att-hero ＋ d-block/d-row(k/v)** 構造へ。開演は `event.start_time`（H:i・null非表示）、**開場(open_time)行は削除**。会場ビュー導線は通常の会場詳細へ（360°は対象外） | detail-topbar / detail-back / detail-edit / att-hero / att-date / att-title / att-meigi / d-block / d-row / k / v / apply-row / lot-select / thumb-grid / venue-view-btn / f-danger |
| `events/create.blade.php`・`events/import-confirm.blade.php` | 開演入力（既存）維持。`.form-label` の縦リズム修正でトーン統一（name属性・送信先は不変） | form-label / warn / imp-row |
| `resources/css/app.css` | v1.3 クラス群を既存トークン（`--color-*`／`--oshi-color`）で移植。`.form-label` に上マージン（前回D1の詰まり一括解消） | 上記すべて |

## 実装手段の決定
| 決定 | 理由 |
|---|---|
| 公演カードの「参戦 N」は `withCount(['attendances' => withoutGlobalScopes])` | events は共有マスタ。件数は削除ガード（withoutGlobalScopes）と同じく全メンバー分を数える |
| ナビは**ボトムナビのまま**5タブ化（mockupの上部pillナビには変更せず） | 指示は「公演を5つ目に追加」。ナビ位置の変更は指示範囲外・既存FAB/レイアウトへの影響を避ける |
| `.form-label` に上マージン付与 | 素のラベルを並べる v1.2/1.3 フォームの密着（前回D1）を1箇所で解消。mockup `.f-field>label` のリズムに一致 |

## 追加対応: 名義（identities）の mockup 追従（2026-07-09・利用者指摘）

初回リリースでは名義を列挙外として据え置いたが、カード配色・フォーム項目順が mockup と乖離との指摘を受け追従。ロジック・name属性・ルートは不変。

| 画面 | 変更 |
|---|---|
| `identities/index` | `.chip`グループタブ → `.fc-tabs/.fc-tab`。白カード＋隅スウォッチ → **担当色でカード全体を淡く塗り（color-mix 7%）＋3px担当色ボーダー＋インライン`.m-swatch`**（mockup .m-card v1.3）。FC名/MEMBERSHIP表記を廃し m-foot に集約。`.m-add` 追加ボタン |
| `identities/show` | `.detail-section` → `detail-topbar` ＋ 塗り`.m-card` ＋ `.d-block`（`.copy-field`/`.cf-k`/`.cf-v`）でログイン情報/個人情報をグループ化、会員期限 d-row、`.apply-row`/`.ar-st` 当落一覧。伏字＋コピーは維持（member_no はspec S4で伏字＝mockupの平文表示より spec優先） |
| `identities/create`・`edit` | **項目順を mockup グルーピングに再配置**: 名義の基本(FC/担当/呼び名/担当色) → ログイン情報(会員番号/パスワード/ID) → 個人情報(氏名/住所/電話/メール/誕生日) → 入会日。`.form-group/.form-label` → `.d-block`＋`.f-field`/`.f-input`/`.f-hint` |
| `app.css` | `.m-card` を塗り版へ更新、`.m-cardhead`/`.m-swatch`/`.fc-tabs`/`.fc-tab`/`.m-add`/`.copy-field`/`.cf-k`/`.cf-v`/`.f-field`/`.f-input`/`.f-hint` 追加、`.swatch-opt` を mockup(border選択)へ |

- テスト: `IdentityV12Test` の当落一覧見出しアサーションを mockup 文言「この名義の申込・当落」に更新（意図＝当落一覧表示・当選率非表示は不変）。**101件緑を維持**。
- rebuild 済み（`.m-cardhead`/`.copy-field`/`.f-input`/`color-mix` の `public/build` 反映を確認）。

## スコープ外（未実施・意図通り）
- C（360°ビュー `.arena-view-btn` / `#scr-arena-view` / `venues.arena_view_key`）。会場詳細の360°導線も出していない。

## QUESTIONS.md 残件（本タスク起票）
| No | 内容 | ステータス |
|---|---|---|
| QV13-6 | spec §3「4タブ＋公演」 vs mockup「公演=5タブ目」の食い違い | 起票（mockup準拠で5タブ仮実装・spec文言更新を要人間確認） |

---

# （過去）現場手帖 v1.2 改修レポート（2026-07-08）

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

## mockup.html と実装CSSの差分（2026-07-08 洗い出し）

ビルドは最新（`public/build` に v1.2 クラス含む・ビルド起因ではない）。マークアップとCSSの構造的不一致を重要度順に記録。

| # | 重要度 | 差分 | 該当 | 状態 |
|---|---|---|---|---|
| D1 | 🔴 最重要 | 登録/編集フォームの縦間隔が密着。v1.2で `.form-group` を廃し素の `.form-label` を並べたが、`app.css` の `.form-label` に上マージンが無い（mockup `margin:14px 0 6px` ↔ app `margin-bottom:6px` のみ）＋`.form-input`に下マージン無し→入力欄と次ラベルが0pxで密着 | attendances/create・edit, events/create・import, lots/create, partials(event-select/venue-select/oshi-picker)。identities/create・edit・auth は `.form-group` 併用で崩れず→画面間で間隔がバラつく | **未修正** |
| D2 | 🟠 中 | `.seat-fields{display:flex;gap:8px}` が app.css 未定義。ビュー側インラインstyleで代替（描画は出るがクラス空振り） | attendances/create・edit | **未修正** |
| D3 | 🟠 中 | 名義詳細のFC情報コピーがmockupの `.copy-list/.copy-row`（会員証様式）でなく旧 `.detail-section` 縦積み。CSSは用意済みだが未使用 | identities/show | **未修正** |
| D4 | 🟡 小 | `.copy-btn` 見た目差（mockup=ピル型/押下反転、app=角丸6px小箱）。動作差なし | app.css:321 | 許容 |
| D5 | 🟡 小 | トークン変数の二重管理（mockup `--oshi` ↔ app `--color-*`＋インライン `--oshi-color`）。実害なし | 全体 | 許容 |
| — | ⚪ 設計差 | body flex中央寄せ↔`.phone margin:0 auto`（等価）/ mockupは`.screen`JS切替↔実装はサーバールーティング`.screen-content`。崩れではない | — | 仕様差 |

**推奨修正**: D1（`.form-label` を `margin:14px 0 6px` に）だけで見た目の崩れはほぼ解消。D2は `.seat-fields` を app.css 追加＋インライン除去。D3はmockup忠実化（見た目変更を伴うため要判断）。
**現状**: 差分の洗い出しのみ完了。修正は未着手（人間の適用可否待ち）。

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
