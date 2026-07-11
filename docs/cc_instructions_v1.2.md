# CC実装指示 — 現場手帖 v1.2

## 0. 前提（着手前に必ず読む）
- 正本は `docs/spec.md`（v1.2）。作業の作法（自律ループ・検証ライン・裁量境界・デプロイ境界）は `CLAUDE.md`（恒久・共通）。**CLAUDE.md は編集しない**。
- v1.2は**新規構築ではなく実装済みコードベースの改修**。破壊的変更（attendances の event_id 化）を含む。
- `main` へ直接コミットしない。`feature/v1.2` を切って作業。**push とマージは人間の最終確認後**。
- spec.md と CLAUDE.md／実装現実が矛盾したら自己解決せず `QUESTIONS.md` へ隔離し、安全側で仮置きして続行。
- 破壊的変更を含むため `docs/deploy_runbook.md` を「人間が上から実行するだけ」の状態に仕上げて終了する。

## 1. このリリースで確定している設計判断（正本の補足・そのまま従う）
1. **他人データのアクセス拒否は404**（存在秘匿＝user_idスコープで不可視）。**写真の削除・編集のみ403**（Policy）。※現行実装がこの通り。**変更不要・退行させないこと**。
2. **参戦・申込の削除規則**（実装済み・維持）：won付きは削除不可（skipped化で表現）／status=applied・全pivot pending|lost は削除可（確認ダイアログ）／削除時は pivot・写真レコードをcascade＋ストレージ実体も削除。
3. **当選率は廃止**。名義詳細は「各申込の当落ステータス（pending/won/lost）一覧」のみ。割合計算はしない。
4. **写真は heic も受付**（jpeg・png・webp・heic）。ただし **heic のデコードとEXIF除去には libheif 前提**。libheif を導入できない環境なら QUESTIONS.md へ隔離し、**安全側（heic拒否）で仮置き**して続行（勝手にheic受付だけ有効化してEXIF除去を素通しにしない）。

## 2. 実装タスク（依存順・PLAN.md に落としてから着手）

### T1. events テーブル新設（共有マスタ）
- migration: `events(event_name string, event_date date, venue_id FK→venues, timestamps)`。**user_id を持たない**。
- Model `Event`：**UserScope を付けない**（venues と同型の共有マスタ）。`venue()` belongsTo。
- 削除ガード：紐づく attendances が1件でもあれば削除不可（venues/グループ削除と同型）。

### T2. attendances の event_id 化【破壊的変更・§8手順厳守】
1. バックアップ（人間がdeploy_runbookで実行。CCはローカルで `.bak`/ダンプを取ってから着手）。
2. migration①：`attendances.event_id`(nullable, FK→events) 追加。
3. データ移行（**生SQL不使用・クエリビルダ＋PHP**）：既存 `(event_name, event_date, venue_id)` の3つ組から events を逆生成（同一3つ組は1件に集約）→ 各 attendance の event_id を埋める。
4. 機械検証：移行前後で各参戦の「公演名・日付・会場」が一致することを **artisanコマンド or テストで突合**。件数・値の不一致で停止（QUESTIONS.md）。
5. 通過後に migration②で `attendances.event_name / event_date / venue_id` を **DROP**（検証を挟むため別マイグレーションに分離）。
6. Model `Attendance`：該当3カラムを fillable/casts から除去。`event()` belongsTo 追加。ビューが参照する公演名・日付・会場は **event 経由**（必要なら読取アクセサ）で解決。venue も event→venue で辿る。

### T3. fc_memberships に email 追加
- migration：`email`（`text` nullable。encrypted 値格納のため text）。
- Model：`casts` に `'email' => 'encrypted'`、`fillable` に `email`。
- IdentityController/IdentityService/フォーム：email を保存対象に追加（バリデーション `nullable|email|max:255`）。
- 名義詳細：email を伏字＋コピー（login_id/password と同機構。**平文はDOMに出さない**・コピー時のみ navigator.clipboard）。

### T4. 担当色プリセット11色セレクト化
- 設定配列を1箇所に：`config/oshi_colors.php`（`return ['#E60033','#F6851F','#F2C500','#8FC31F','#00A960','#00AFEC','#1D6EC9','#8957A1','#EE87B4','#FFFFFF','#2B2B2B'];`）。**金 #C9A63C は含めない**。増減はこの配列のみで行う。
- 名義 作成/編集フォーム：ネイティブ `type="color"` を廃止し、この配列から生成するセレクト（丸スウォッチ）に。白 #FFFFFF はカード上で枠線必須。
- バリデーション：`oshi_color` を `nullable|in:<配列>`（プリセット外を弾く）。DBは hex 文字列のまま・移行不要。

### T5. events CRUD・検索付きセレクト・重複警告
- ルート（全ユーザー可・user_idスコープ無し）：`/events`(一覧) `/events/create` `/events`(store) `/events/import` `/events/import/parse` `/events/import`(store)。
- 会場は検索付きセレクト（既存venues部分一致→無ければ新規＝Placesオートフィル。失敗・キー無しは手入力フォールバック・登録をブロックしない・レスポンスは永続キャッシュしない）。
- **重複警告**：同一 venue_id × 同一 event_date の既存 event があれば警告表示（**ブロックしない**＝昼夜2公演があるため）。

### T6. 一括インポートを events へ移設
- `/lots/import` を廃止し `/events/import` へ。共有マスタ（events）投入のみ・**名義選択は無し**・全ユーザー可。
- テキスト行分解→日付・会場・公演名を候補抽出（空欄・余計な記号は解析時に除去）。解析不能行も**捨てず空欄で残す**。確認テーブルで編集→一括登録。

### T7. 参戦登録の作り替え
- 公演を**検索付きセレクト**（events部分一致・インクリメンタル絞り込み）で選択→ event_id を保存。
- 選択で日付・会場・アクセスを**読取表示**（自動・参戦テーブルには持たない）。手入力は**座席3フィールドと写真のみ**。名義は複数選択→pivot生成。
- マスタに無ければ `/events/create` へ誘導。

### T8. 公演日経過→「参戦した？」確認
- ホームに、status=planned かつ event_date を過ぎた参戦の確認UI。確定操作で attended に遷移。**自動遷移はしない**（譲渡・不参加を attended 化しない）。skipped への手動変更も可。

### T9. 当選率の撤去【決定④】
- `FcMembership::winRate() / applicationCount() / winCount()` を削除。
- `tests/Unit/WinRateTest.php` を削除。
- 名義詳細ビュー：当選率表示を「各申込の当落ステータス（won/lost/pending）一覧」に置換。
- ※`result` 集計そのもの（当落の表示）は残す。消すのは「率」の算出・表示のみ。

### T10. heic 受付【決定①・安全弁つき】
- 目標：写真受付に heic を追加（`mimes:...,heic`）＋ **EXIF除去を担保**。
- EXIF除去は現状 GD 再エンコードだが GD は heic 非対応。**imagick + libheif** に切替えて heic をデコード＆メタデータ除去する。
- **libheif を導入できない環境なら**：QUESTIONS.md に「heic受付は libheif 未導入のため保留」と記録し、**heic は拒否のまま（安全側）** で仮置きして続行。EXIF除去を保証できないまま heic を通すことは禁止。
- 導入できた場合：`PhotoTest` の「heicアップロードは拒否される」を「heic も受付・EXIF除去される」へ**書き換え**。

## 3. テスト（§8-9・全て機械検証で担保。目視照合させない）
- **T2移行の突合**：移行前後で各参戦の公演名・日付・会場が一致（件数＋値）。
- **events削除ガード**：紐づく参戦0件のみ削除可。
- **公演重複警告**：同一venue×同一dateで警告が出る・ただしブロックしない。
- **参戦登録**：公演選択で event_id が保存される／手入力は座席・写真のみ。
- **参戦確認**：planned＋event_date経過→確定でattended。**自動遷移しない**ことも検証。
- **email**：encrypted保存・伏字表示・コピー導線。
- **oshi_color**：プリセット外を弾く／hex保存。
- **heic**：受付できる（libheif導入時）。未導入なら拒否のまま（QUESTIONS.md明記）。
- **退行させない既存テスト（緑維持必須）**：他人データ404・写真削除403・削除規則（applied/lost/won）・当選昇格・タイムラインapplied非表示・更新期間の境界（1月/2月年跨ぎ/うるう年/受付境界日）・招待二度使えない・EXIF除去。

## 4. 検証ライン（CLAUDE.md準拠・全YESで完了）
- V1 `php artisan migrate:fresh`（exit 0）※ローカルSQLite。
- V2 起動後 `/up` が200。
- V3 `php artisan test` 全通過。
- 破壊的変更（event_id）は本番で人間が実行 → `docs/deploy_runbook.md` を完成させる（バックアップ→migration①→移行検証→migration②DROP の順を人間が上から実行できる形）。

## 5. 終了時成果物
- `REPORT.md`（V1〜V3判定＋実装手段の決定記録＋QUESTIONS残件）
- `QUESTIONS.md`（heic/libheif の可否・矛盾・3回失敗の記録。無ければ「なし」）
- `docs/deploy_runbook.md`（event_id移行と email/events 追加の本番反映手順）

## 6. やらないこと（スコープ外・提案もしない）
- 管理者ロール（is_admin/Gate）— events は全ユーザー共有マスタ。招待制が実質の認可。
- 座席図ビジュアル／更新日数カウントダウン・通知／スクショOCR／公演情報の外部取得。
