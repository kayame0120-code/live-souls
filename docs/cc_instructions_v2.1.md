# CC作業指示書 v2.1 (2026-07-11)

## 参照仕様
本書は `docs/spec.md`(現行v2.4相当)の実装順序・境界線を指示するものであり、仕様そのもの(何を作るか)はspec.mdが正本。本書とspec.mdが矛盾する場合はspec.mdを優先し、矛盾自体をQUESTIONS.mdに記録すること。
恒久ルール(自律ループ・検証ライン・裁量境界・デプロイ境界)は `CLAUDE.md` に従う。本書はアプリ固有の作業順序のみを扱う。

## v2.0からの前提の上書き(必読)
- **Ollama**: 同一Flyマシン同居は不採用に確定(QV20-6)。旧v2.0 §5「段階的に本番投入」の手順一式は撤回。6章に置き換え
- **§1凍結リストの例外**: `fc_memberships`へ`group_member_id`(FK, nullable)を1カラムのみ追加する。既存カラムの変更ではなく追加のみ、他は凍結のまま
- `events.start_time`のアクセサ/ミューテータ方式(QV13-5)・5タブナビ(QV13-6)は対応済み。再作業不要

## 大原則
- `[未決]` とマークされた項目は実装しない。QUESTIONS.mdに積んで片倉の判断を待つ。埋め方を推測しない。
- 動いている既存ロジックを壊してから作り直さない。2章「完了済み」は再実装・再設計しない。
- 1タスク1PR相当。複数エピックを1つの変更にまとめない。
- spec.mdに`[確定]`として実カラムが書かれていない項目は実装しない(「型を踏襲」だけの記述はspec側の不備。QUESTIONS.mdへ)。

---

## 1. 触ってはいけないもの
v2.0 §1の凍結リストをそのまま維持(`persons`/`identity_groups`/`fc_memberships`の既存カラム・`venue_notes`粒度・`attendances.companion`/`memo`・座席3分割・写真ロジック・E1・`FcMembership`計算式)。
**例外は上記「v2.0からの前提の上書き」の`group_member_id`追加のみ**。

## 2. 既に完了済み(再着手・再検証不要)
current_code.html確認済み。以下は実装済みのため触らない：
- 公演一覧/公演詳細のツアーカード2階層(`EventController::index`)
- 当落一覧/当落詳細のツアーカード2階層(`LotController::index`/`showByTour`)
- 名義一覧のFCタブ束ね(`identities/index.blade` fc-tabs)
- ホーム更新通知・チケット確認・開場時間表示(v2.0 T1〜T3)
- 担当色の手動11色スウォッチ(`config/oshi_colors.php` + `partials/oshi-picker.blade.php`)。**4章の担当メンバー選択はこれを流用し、作り直さない**

## 3. すぐ着手できるタスク(依存なし・並行可)

### 3.1 idol_groups / group_members
- `member-colors_STARTO_v0_1.md`を`docs/`配下に配置し、マイグレーション+シーダーを実装(spec 3章のカラム定義)
- **NEWSの色はmdファイルではなくspec 3.2の訂正表(単色)を使う**。mdのNEWS行は使用禁止
- 完了の定義: シーダー実行後、STARTO全グループが投入され、NEWSが単色(小山=紫/加藤=緑/増田=黄)であることをテストで確認

### 3.2 events.application_deadline / announce_date
- カラム追加のみ(datetime nullable / date nullable、spec 3章)
- 締切超過判定はサーバー側`now()`比較(spec 4.6)。手入力での更新経路(公演詳細/当落詳細の編集画面)も用意

### 3.3 setlists / setlist_items
- テーブル作成(spec 3章のカラム定義)
- 手動登録フォーム(公演詳細→セットリスト画面)を実装。AI一括登録は5章で別途

## 4. 名義複製・担当メンバー選択(本実装・v2.0では未着手)
v2.0では`[未決]`のためラフ案止まりだったが、HANDOFF§3.1/3.2の承認によりspecで`[確定]`化された。
- 担当メンバー選択：グループ選択→公式メンバーが色付きで並ぶ→選択で`oshi_color`に自動反映→**既存oshi-pickerで手動上書き**(2章参照・新規UIは追加ステップのみ)
- 名義複製：名義詳細に複製ボタン→複製画面。個人情報(住所/電話/メール/誕生日)は`person`から引き継ぎ表示のみで入力させない。FC固有情報のみ新規入力、保存時は既存`person`のIDを参照(重複登録禁止)
- 完了の定義: 複製後も`persons`が重複登録されないこと、担当メンバー選択で`group_member_id`と`oshi_color`が正しく紐づくことをテストで確認

## 5. AI一括登録基盤(LlmService) — 順序厳守
1. `LlmService`インターフェースを作成。`LLM_DRIVER`環境変数(`ollama`/`openai`)で実装を切替
2. ローカル=`ollama`、本番=`openai`等クラウド(APIキーは人間が`.env`にセット)。**同一マシン同居はしない**
3. キュー(queue worker)経由で実行
4. 公演一括登録：既存`events.import`(parse→confirm→store)のステートレス構造(DBに一時テーブルを持たない)を踏襲し、`EventImportParser`の代わりに`LlmService`を呼ぶ(spec 4.1)
5. 動作確認後、`EventImportParser`/`EventImportParserTest`を削除(6章)。**先に壊さない**
6. セットリスト一括登録：4と同型、対象`event`単位で確認画面から`setlists`/`setlist_items`へ登録
7. 当落締切一括登録：会場名+日付で該当`events`を自動マッチ、未マッチは確認画面で手動選択(spec 4.6)。手入力フォールバックは3.2で用意済み
各ステップ後に成功判定基準(メモリ使用量・JSON化精度など)を明示してから次へ進む(evidence-first-ops)。

## 6. 削除対象(実施タイミング厳守)
| 対象 | 削除タイミング |
|---|---|
| `EventImportParser` / `EventImportParserTest` | 5章ステップ5(公演一括登録の動作確認)後 |

## 7. Deploy1(本番投入)
- 対象：2〜5章の全機能(**360度ビューを除く**、spec 9章)
- `venues.arena_view_key`カラムはそのまま含める(実データなしでも実害なし)
- 会場詳細の360度ビュータブUI(プレースホルダー)もそのまま含めてよい(spec 9章)
- 検証ラインはv2.0と同方式(`migrate --force`→`/up` 200→`artisan test`)。REPORT.mdに追記
- `main`ブランチで実施。arena-view統合(Deploy2)は別ブランチで別途、着手しない

## 8. 保留(未決事項、着手禁止)
| No | 内容 |
|---|---|
| 1 | `venues.arena_view_key`のマージ方法。arena-viewエンジン(v3)完成後、別ブランチで判断(spec 7章・9章) |

## 9. 完了の定義(このラウンド全体)
- 3〜4章の機能が実装・テスト済み
- 5章がステップ7まで到達、`EventImportParser`削除済み
- 7章のDeploy1が本番投入され、検証ラインが通過している
- 8章の未決事項はQUESTIONS.mdに反映されたまま(着手なし)

## 変更履歴
- v2.1 (2026-07-11): spec v2.1〜v2.4の確定事項を反映。idol_groups/group_members・名義複製/担当メンバー選択・setlists・締切カラム・LlmService移行・Deploy1本番投入を追加指示。Ollama段階的投入手順(v2.0 §5)を撤回。current_code.html突合せにより「公演/当落2階層」「FCタブ束ね」「oshi-picker」が実装済みであることを確認し2章へ移動(再着手防止)。
- v2.0 (2026-07-10): spec_v2.0.mdの確定事項をもとに新規作成
