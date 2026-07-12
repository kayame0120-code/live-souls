# QUESTIONS.md — 現場手帖

## v2.7 残件（2026-07-12・R2対応後）

### QV27-1: リカバリーキー紛失警告の独立UI画面

- **基準根拠**: security_criteria No.7「リカバリーキーを提示できない場合の復旧不能がUI上で明示されているか」
- **現状**: セットアップモーダル(`e2e-ui.js showRecoveryKeyScreen`)内に「このキーは今回しか表示されません。パスワードを忘れた場合、このキーがないとFC情報は二度と復元できません」テキストが存在する
- **不足**: 基準が求める水準が「モーダル内テキスト」で足りるか「独立した注意喚起画面」が必要かは判定者次第。安全側でQUESTIONSに隔離

### QV27-3: LLM呼び出しのキュー化

- **根拠**: spec 4.1「キュー経由で実行」/ cc_instructions R3-1
- **実測**: テキスト2行の同期呼び出しで3.76秒。画像5枚は60秒タイムアウトのリスク
- **仮置き**: 同期呼び出しのまま（テキスト・小規模画像では実用可。画像5枚の本番運用前にキュー化が必要）

### QV27-4: APP_LOCALEの.env変更

- **根拠**: spec 0章「APP_LOCALE=ja」/ cc_instructions T4
- **状況**: config/app.phpのデフォルトはjaに設定済み。lang/ja/一式配置済み。ただし.envに`APP_LOCALE=en`が残存しており上書きされている
- **対応**: 人間が.envの`APP_LOCALE=en`を`APP_LOCALE=ja`に変更する（CCは.env変更禁止規約）

---

## v2.1 残件（2026-07-11）

### QV20-2: `venues.arena_view_key`のマージ方法

- **spec根拠**: spec v2.4 §7 No.1 / §9 Deploy2
- **方針確定**: arena-viewエンジン(v3)完成後に別ブランチで統合。Deploy1にはカラムのみ含める（実害なし）。
- **残**: 360度ビュータブUI（プレースホルダー）はDeploy1に含めてよいが、実データ連携はDeploy2。

---

## v2.1 で解決済み（v2.0 未決からの昇格）

| No | 内容 | 解決 |
|---|---|---|
| QV20-1 | group_membersカラム設計・oshi_colorとの関係 | spec v2.3/v2.4で確定。B案（oshi_colorは自動反映＋手動上書き可）で実装済み |
| QV20-3 | setlists/setlist_items設計 | spec v2.2でカラム確定。実装済み |
| QV20-4 | AI一括登録確認方式 | spec v2.2で「DBテーブルなし・ステートレス」に確定。実装済み |
| QV20-5 | 名義複製UI | spec v2.1でHANDOFF§3.2承認。名義詳細→複製画面で実装済み |
| QV20-6 | Ollama本番投入可否 | C案確定。LlmService抽象化＋LLM_DRIVER切替で実装済み |

---

## 未解決・保留（安全側で仮置き中）

### QV13-1 ✅クローズ: 開演の源は events.start_time に一本化（片倉指示・2026-07-09）

- 片倉より「QV13-1 を解決済み扱いにする」と明示指示。開演表示の源は `events.start_time` に一本化で確定。
- 実装：home「次の現場」・参戦詳細・ツアー詳細/日程・カスケード②ラベルの開演は全て `event.start_time` を参照。参戦側 `attendances.start_time`/`open_time` は開演源に使わない（物理削除は本移行の対象外・温存）。
- v1.4 指示書 §0 は「未解決」と記していたが、上記指示で解消。tours の公演名張り替え（tours.name+event_label）とは別コミットで分離済み。

### QV14-1: v1.4/v1.5 の「参戦登録・申込登録の当落入力欄」— 現行は既に pending 自動生成（要認識のみ）

- v1.5 §2-3 は「申込登録の当落入力欄を作成フォームに置かない」。現行実装は v1.2 以来 pivot=pending 自動生成で、フォームに当落欄は元々無い。→ **是正不要**（画面表記を実態に合わせただけ）。テストで pending 固定を担保（TourHierarchyTest::v3）。

### QV13-6: spec §3「4タブ＋公演」 vs mockup「公演＝5タブ目」の食い違い（要人間確認）

- **発見日**: 2026-07-09（v1.3 view 再テンプレート時）
- **矛盾**: `docs/spec.md` §3 のタブ構成は「**ホーム / 参戦記録 / 名義 / 当落 の4タブ** + 公演 + 会場詳細 + 認証・招待」で、公演を4タブの外（サブ画面）扱い。
  一方 `docs/mockup.html`（v1.3・デザインの正）の `nav`(L522-528) は「**ホーム/参戦記録/名義/当落/公演 の5ボタン**」で公演をメインタブに昇格。
- **仮実装（レイアウトは mockup を正）**: `components/app-layout.blade.php` のボトムナビを **5タブ**化し「公演」(`events.index`)を5つ目に追加（`.bottom-nav` を `repeat(5,1fr)`）。
  これに伴い前任が暫定でホームに置いた「公演マスタ」`link-row` 導線は撤去し、公演入口を5タブ目へ一本化。
- **決めてほしいこと（片倉）**: spec §3 の文言を「**4タブ＋公演** → **5タブ（公演を含む）**」へ更新してよいか。
  ※挙動・ルートは不変（`events.index` は既存）。本件は spec の記述整合のみ。
- **spec 反映**: OK が出れば spec.md §3 のタブ構成を5タブ表記へ更新する。

### QV13-1 ✅解決: 開演の源は events.start_time に一本化（2026-07-09）

- **決着**: spec.md §4 events に「開演の源は events.start_time に一本化する」を明記（追記済み）。
- **実装**: 参戦詳細（`attendances/show`）・次の現場（`home`）の開演表示を `attendance->event?->start_time` 参照へ切替。公演カード（`events/index`）・公演登録（`events/create`）・確認テーブルに開演を追加。
- **残処理**: 既存 `attendances.start_time` / `open_time` カラムは物理削除しない（§8対象外）。開場(open_time)は event 相当が無いため参戦側を暫定表示のまま。参戦記録一覧カード（`attendances/index`）への開演表示は任意の追加項目として未実施（必要なら次段）。
- （以下は当初記録）

### QV13-1（当初記録）: 開演の真実の源が二重化（events.start_time ↔ attendances.start_time/open_time）

- **発見日**: 2026-07-09（v1.3 A 着手時）
- **矛盾**: cc_instructions v1.3 §2 は「開演は event 由来で表示・昼夜同定も event.start_time」とする一方、
  同§で「attendances 側のスキーマ変更は不要」とも言い、**既存の `attendances.start_time` / `attendances.open_time` に一切言及がない**。
  実装では既に参戦が自前の start_time/open_time を持ち、`home.blade`（次の現場）・`attendances/show`（開場/開演）で表示に使用中。
  → 同一参戦で「参戦側 start_time」と「公演側 start_time」が食い違い得る（どちらを開演の正とするか未定）。
- **安全側で仮置き**: **events.start_time の追加（migration＋Event モデル）だけ確定**し、
  表示の張り替え（参戦カード・次の現場・参戦詳細を event.start_time 源へ移す）は**保留**。
  既存の参戦側時刻表示は**現状のまま温存**（破壊しない）。doc も「attendances 側スキーマ変更不要」なのでカラム削除もしない。
- **決めてほしいこと（片倉）**:
  1. 開演表示の正は「公演(event)」に一本化するか、参戦側の手入力時刻を残すか。
  2. 一本化するなら、既存 attendances.start_time/open_time の扱い（表示から外すだけ / 将来 deprecate）。
- **spec 反映**: 本件は spec.md v1.3 側で「開演の源」を明記して解消すべき（現 spec.md は v1.2 で start_time の記述なし＝QV13-4）。

### QV13-2 ✅解除: B（一括インポート解析のPHP移植）実装済み（2026-07-09）

- **解除**: `docs/EventImportDemo.jsx` 投入を確認。`App\Services\EventImportParser` へ parse() を1:1移植（正規表現・NOISE辞書・状態機械・境界判定を写経・改善なし）。
- **配線**: `EventController::importParse/importStore` を新パーサへ。重複キーを `venue_id × event_date × start_time` に更新（`EventService::findDuplicates`）。確認テーブルに開演列・未解析行表示を追加。
- **検証**: `tests/Unit/EventImportParserTest`（SAMPLEで6公演・tour・昼夜別・ノイズ遮断・未解析保持）＋`tests/Feature/EventDuplicateKeyTest`（重複キー・昼夜別レコード）。※実行は人間がWSLで（下記REPORT参照）。
- **残**: 旧 `LotImportService` は未使用化（削除はせず・自身のテストは緑のまま）。
- （以下は当初記録）

### QV13-2（当初記録）: B（一括インポート解析のPHP移植）は原本欠落で未着手

- **発見日**: 2026-07-09
- **doc根拠**: v1.3 §3「`EventImportDemo.jsx` の `parse()` が正。挙動を変えず1:1で写経・改善禁止」。
- **状況**: **`EventImportDemo.jsx` がリポジトリ・アップロードのどこにも存在しない**（.jsx 皆無・全文grep済み）。
  写経すべき原本（正規表現・NOISE/HEAD定数・状態機械）と、T1 の機械検証に必要な **SAMPLE入力＋期待45件** が無い。
- **仮置き（安全側）**: 実装しない。パーサを推測で書くこと（＝独自決定）は禁止規定により回避。
- **解除条件**: `EventImportDemo.jsx`（parse本体・定数・SAMPLE入力・期待出力を含む）を `docs/` か `tests/fixtures/` に投入。

### QV13-3: C（360°会場ビュー）は arena-view 資産欠落で器のみ／未着手

- **発見日**: 2026-07-09
- **doc根拠**: v1.3 §4「中身は arena-view の `venue_engine_template.html` を Blade に組込／key→会場設定 対応表を移植」、§4-4/4-5「座席3段フォールバックは seat→座席ID の正規化対応表で引く」。
- **状況**: **`venue_engine_template.html`・key→会場設定 対応表・seat正規化対応表 が一切存在しない**（別プロジェクト arena-view 側）。
  器（route/controller/404/導線出し分け/3段フォールバックの制御）は書けるが、①②の座席解決も 360°実体も入れられず、③(会場のみ)の空画面にしかならない。
- **仮置き（安全側）**: 未着手（空の器を先行させるかは片倉判断・前回選択肢②）。
- **解除条件**: arena-view 一式（HTMLテンプレート＋key対応表＋seat正規化表）を投入。

### QV13-4 ✅クローズ: v1.3 の正本 spec.md 配置済み（2026-07-09）

- `docs/spec.md` が v1.3 に更新されたことを確認（ヘッダ「v1.3」）。開演の源（QV13-1）も §4 に追記済み。ガバナンス上の穴は解消。
- （以下は当初記録）

### QV13-4（当初記録）: v1.3 の正本 spec.md が未更新（現物は v1.2）

- **発見日**: 2026-07-09
- **矛盾**: cc_instructions v1.3 は「正本: docs/spec.md v1.3」を前提に書かれているが、**`docs/spec.md` の現物は v1.2**（start_time・arena_view・昼夜同定の記載なし）。
- **影響**: 本書の衝突規定＝spec.md 優先。v1.2 に無い A/B/C は形式上"spec外"のまま。ガバナンス上、spec.md を v1.3 へ更新してから本実装を確定させるのが筋。
- **仮置き**: A のコア（追加のみ・非破壊・可逆）は先行実装。B/C は spec.md v1.3 と原本が揃うまで保留。

### QV13-5 ✅解決（要合意・指示書からの逸脱）: start_time は cast を廃しアクセサで H:i:s 保存に変更（2026-07-09）

- **実証**: `EventDuplicateKeyTest > 開演違いは重複扱いにならない` が FAIL（whereTime が 0 件）。指示書指定の `datetime:H:i` cast が `time` 列に `Y-m-d H:i:s` を書き、SQLite突合ズレ＋**PostgreSQLでは INSERT 自体が失敗する**ことを裏付けた（他100件は緑）。
- **対応**: `Event` の `casts()` から `start_time` を外し、**アクセサ/ミューテータ**へ差し替え（get=Carbon／set=`H:i:s` 文字列）。`EventService::findDuplicates` は突合値を `H:i:s` へ正規化して `where` 比較。表示側の `->format('H:i')` は変更不要。
- **指示書との関係**: cc_instructions v1.3 §1-1 の `'datetime:H:i'`（[確定]）からの**意図的な逸脱**。理由は上記の time 列非互換（本番破壊）。spec/指示書側の cast 記述を本アクセサ方式へ改める要合意（片倉）。
- **再検証**: `php artisan test --filter=EventDuplicateKeyTest` が緑になることを確認（人間がWSLで実行・下記）。
- （以下は当初記録）

### QV13-5（当初記録）: events.start_time の cast（`datetime:H:i`）× `time` カラムの保存挙動は要検証

- **発見日**: 2026-07-09（A/B実装時）
- **論点**: cc_instructions v1.3 §1-1 が指定する cast は `'start_time' => 'datetime:H:i'`。だが列型は `time`。
  Eloquent の datetime cast は保存時に既定で `Y-m-d H:i:s` へ整形するため、`time` カラム（特に **PostgreSQL**）に日付付き文字列を入れて弾かれる／`findDuplicates` の `whereTime` 突合がズレる恐れがある。
- **状況**: 指示書の指定（[確定]）通りに実装済み。SQLite（ローカル）は緩く通る可能性が高いが、**本番 PostgreSQL で `EventDuplicateKeyTest` と登録の実挙動を要確認**。
- **不都合が出た場合の候補**: cast を外して素の文字列（`H:i`）保存にする／`time` 用のカスタムキャストにする／列を `string` にする。いずれも spec/指示書の変更を伴うため、勝手に変えず本欄で合意してから。
- **検証手段**: `php artisan test --filter=EventDuplicateKeyTest` の生出力を REPORT.md に貼る。

---

### QV12-1: heic 受付は libheif 未導入のため保留（安全側=拒否で仮置き）

- **発見日**: 2026-07-08（v1.2改修）
- **spec根拠**: §4「heicのデコードとEXIF除去には libheif 導入が前提（未導入では安全に保存できないため受付不可）」/ 指示書T10「libheifを導入できない環境なら QUESTIONS.md へ隔離し、安全側（heic拒否）で仮置き」
- **状況**: 実行環境（ローカル PHP 8.2.32）は **GD のみ・imagick/libheif なし**（`imagick=no` を確認）。
  GD は heic をデコードできず、**再エンコードによる EXIF 除去を保証できない**。
- **仮置き（安全側）**: 写真受付は **jpeg/png/webp のみ**。heic は拒否のまま。
  EXIF除去を素通しにして heic を通すことは禁止（指示書T10）——のためこの判断。
- **解除条件（人間の infra タスク）**: 本番/開発環境に **imagick + libheif** を導入する。
  導入後の対応: `AttendanceController::validatedData()` の `mimes` に `heic` を追加、
  `PhotoService::reencodeWithoutMetadata()` を imagick 経由の heic デコードに対応、
  `PhotoTest::test_heicアップロードは拒否される` を「heicも受付・EXIF除去」へ書き換え。
- **テスト現況**: 現環境の安全側挙動（heic拒否）をテストで担保済み（`PhotoTest`）。
  libheif導入後の受付テストは infra 導入後に有効化する。
- **deploy_runbook** に libheif 導入手順を記載済み。

---

## クローズ済みの記録（監査用）

### v1.2 移行の現物値・検証（spec §8 記録義務）
- 移行前 attendances 1件: `event_name="なにわ男子 LIVE TOUR 2026 「ND⁵」" / event_date=2026-08-15 / venue_id=1`
- events を逆生成 → 1件に集約、attendances.event_id=1 を付与。
- マイグレーション内蔵の機械検証＋`genba:verify-event-migration` で公演名・日付・会場の一致を確認（生出力はREPORT.md）。
- 旧 event_name / event_date / venue_id は検証通過後に DROP。
- バックアップ: `database/database.sqlite.v1.1-pre-v1.2.bak`（md5一致確認済み）。

### v1.1.1 で確定済み（継続有効）
- Q3 参戦・申込の削除規則（won無し削除可 / won付き削除不可）→ v1.2 spec §5 に継承・実装/テスト済み。
- Q5（v1.1のheic見送り）→ v1.2 で「libheif前提の受付」に方針変更。現環境では上記 QV12-1 の通り保留。

### v1.0/v1.1 のクローズ済み質問
- Q1 laravel-starter 未セットアップ → 解決済み。
- Q2 E1 グループ削除 → A案（配下0件時のみ削除）で確定・実装済み。
- Q4 当落結果の入力動線 → 実装済み（当落画面セレクト＋参戦詳細）。
