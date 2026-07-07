# 現場手帖 — アプリ仕様 spec.md v1.0 (2026-07-07)

> 本書は現場手帖の**アプリ固有仕様**。作業プロトコル・検証ライン・裁量境界・デプロイ境界は
> `CLAUDE.md`（恒久ルール）に従う。本書と CLAUDE.md に矛盾があれば自己解決せず QUESTIONS.md に隔離し、
> 安全側（実装しない側）で仮置きして続行すること。
> ステータス凡例: [確定]=変更禁止 / [既定]=デフォルト・変更は要確認(QUESTIONS.md) / [未決]=実装禁止

---

## 0. アプリ固有の規約（CLAUDE.md の技術前提に上乗せする項目のみ）

CLAUDE.md 記載済みの前提（Laravel12/PHP8.2 / ローカルSQLite・本番Fly Postgres / ENUM禁止 / main直コミット禁止 等）は繰り返さない。本アプリ固有の規約は以下。

| No | 規約 | ステータス |
|---|---|---|
| 0-1 | 認証は **Fortify**。Breeze不使用。会員登録は**招待制**（`Features::registration()` を無効化し `/register/{code}` 経由のみ許可） | [確定] |
| 0-2 | 機密情報（FCパスワード・電話・住所・login_id）は Eloquent の **`encrypted` キャスト**で暗号化保存。ハッシュ化しない（本人が復号して使うため） | [確定] |
| 0-3 | **マルチテナント**。全ドメインテーブルを `user_id` でスコープ。他ユーザーのデータへのアクセスは403（グローバルスコープ or 明示的where必須） | [確定] |
| 0-4 | 生成元は自作 **laravel-starter**（Fortify招待制入り）。現場手帖はその初回適用先 | [確定] |
| 0-5 | 状態カラムは string + `in:...` バリデーション（CLAUDE.md のENUM禁止を本アプリのstatus/resultに適用） | [確定] |

**DB二層の注意（CLAUDE.md「SQLite/PGで挙動が割れる書き方の禁止」の適用）**: 日付計算（年齢）・集計（当選率）はDB固有関数に依存せずPHP側 or Eloquent標準機能で行う。生SQL・DB方言を使わない。

## 0.1 前提とする既存の土台

| 土台 | 確認方法 | ステータス |
|---|---|---|
| 空Laravelアプリ | 片倉が Fly.io にデプロイ済み・表示確認済み（2026-07-07） | [確定] |
| laravel-starter | 空Laravel→starter化はこれから。**本仕様の前工程**。未セットアップの形跡があれば QUESTIONS.md へ記録して停止 | [確定] |

---

## 1. 概要と目的

複数のFC名義を持ち複数端末を使うヲタクが、**バラバラなメモ帳管理を1つに畳む**ためのWebアプリ。
中核価値は「いつ・どの名義で入って・どこの席だったか」を後から一目で引けること。
名義（FC会員情報＝ログイン情報を含む金庫）、参戦記録（主役）、会場ナレッジ（遠征インフラ再利用）を1本に統合する。
当面は片倉個人＋招待した友人数名での利用。不特定多数には開かない。

## 2. ユーザーストーリーと受け入れ条件

| No | ストーリー | 受け入れ条件（YES/NOで判定可能） | ステータス |
|---|---|---|---|
| S1 | 参戦から帰る電車内で記録したい | 参戦登録フォームが、日付・公演名・会場・座席・名義の入力だけで保存でき、保存後に参戦記録タイムラインへ反映される | [確定] |
| S2 | 過去を「いつ・どの席」で引きたい | 参戦記録画面で年フィルタを切替でき、各レコードに日付・会場・座席・名義が常に表示される | [確定] |
| S3 | 複数名義をグループで仕分けたい | 名義画面上部のグループタブを切替えると、そのグループの名義カードだけ表示。グループは追加・改名・削除できる | [確定] |
| S4 | FCログイン情報を安全に取り出したい | 名義詳細でパスワードは伏字表示され、コピーボタンでクリップボードに入る（画面に平文を出さない）。会員番号・login_idも同様 | [確定] |
| S5 | どの名義で何を申込み何が当たったか | 当落画面で公演ごとに申込名義が行で並び、各行に当選/落選/未発表が表示される | [確定] |
| S6 | 名義ごとの当選率を知りたい | 名義詳細に、その名義の申込数・当選数・当選率が pivot から自動集計される（手入力欄は無い） | [確定] |
| S7 | 遠征のたび会場情報を再利用したい | 会場詳細に共有情報（住所・最寄駅・キャパ）と自分専用メモ（定宿・交通費・個人メモ）が両方表示される | [確定] |
| S8 | 友人に配りたい | 招待コードを発行でき、そのコードを持つ人だけが会員登録できる。URLを踏んだだけの第三者は登録できない | [確定] |

## 3. 画面

タブ構成: **ホーム / 参戦記録 / 名義 / 当落** の4タブ + 会場詳細（参戦から遷移）+ 認証・招待。
デザインは `docs/mockup.html`（世界観「現場手帖 — FC事務局×ヲタ活手帳」）に準拠。名義の担当色が唯一の有彩色。

| 画面名 | URL | 主な表示要素 | 遷移先 | 認証 | ステータス |
|---|---|---|---|---|---|
| ログイン | `/login` | Fortify標準 | ホーム | 不要 | [確定] |
| 招待付き登録 | `/register/{invite_code}` | 招待コード検証後にFortify登録フォーム | ホーム | 不要 | [確定] |
| ホーム | `/` | 次の現場（カウントダウン）/ 積み上げ（今年の参戦・当落待ち・名義数）/ 直近の記録 | 各タブ | 要 | [確定] |
| 参戦記録 | `/attendances` | 年フィルタ / 参戦カード一覧（日付・会場・座席・名義・同行・メモ） | 参戦詳細・会場詳細 | 要 | [確定] |
| 参戦登録 | `/attendances/create` | 日付・公演名・会場・座席raw・名義選択・status | 参戦記録 | 要 | [確定] |
| 参戦詳細 | `/attendances/{id}` | 全項目 + 申込/当選名義（pivot）+ 会場詳細への導線 | 会場詳細・編集 | 要 | [確定] |
| 名義 | `/identities` | グループタブ / 名義カード一覧 / FAB非表示 | 名義詳細・グループ管理 | 要 | [確定] |
| 名義詳細 | `/identities/{id}` | FC情報（伏字＋コピー）/ 申込・当選集計・当選率 | 編集 | 要 | [確定] |
| グループ管理 | `/identity-groups` | グループの追加・改名・削除・並び替え | 名義 | 要 | [確定] |
| 当落 | `/lots` | 当落待ち / 結果。公演ごとに名義行＋当選/落選/未発表 | 参戦詳細 | 要 | [確定] |
| 会場詳細 | `/venues/{id}` | 共有情報（住所・最寄駅・キャパ）+ 自分のメモ（定宿・交通費・メモ）+ その会場の参戦履歴 | 参戦詳細 | 要 | [確定] |
| 招待管理 | `/invitations` | 招待コード発行・一覧・失効 | — | 要 | [確定] |

### 空状態（データ0件）の表示
| 画面 | 0件時の表示 | ステータス |
|---|---|---|
| ホーム | 「次の現場」欄に「予定なし」/ 積み上げは全て0 / 直近の記録は「まだ記録がありません」 | [既定] |
| 参戦記録 | 「まだ参戦記録がありません。＋から最初の1件を記帳しましょう」 | [既定] |
| 名義 | グループ0件時は「グループを作成」導線のみ。名義0件時は「名義を追加」導線 | [既定] |
| 当落 | 「当落待ちの申込はありません」 | [既定] |
| 会場詳細 | 個人メモ未記入時は各項目「未記入」プレースホルダ | [既定] |

## 4. データ（テーブル定義）

凡例: 🔒=`encrypted`キャスト（暗号化保存）。全テーブルに `user_id`（venues除く）でマルチテナントスコープ。

### users（Fortify標準＋拡張）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id / name / email / password | — | Fortify標準（email unique / passwordハッシュ） | [確定] |
| invited_by | bigint | nullable, FK→users.id | [確定] |

### invitations
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| code | string | unique（推測困難なランダム文字列） | [確定] |
| issued_by | bigint | FK→users.id | [確定] |
| used_by | bigint | nullable, FK→users.id | [確定] |
| used_at | timestamp | nullable | [確定] |
| expires_at | timestamp | nullable | [既定] |

### identity_groups（名義のフォルダ／タブ）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| user_id | bigint | FK→users.id | [確定] |
| name | string | | [確定] |
| sort_order | integer | default 0 | [確定] |

### persons（名義人）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| user_id | bigint | FK→users.id | [確定] |
| name | string | | [確定] |
| birth_date | date | nullable（年齢はここから自動計算・年齢カラム不保持） | [確定] |
| phone | string | 🔒 nullable | [確定] |
| address | string | 🔒 nullable | [確定] |
| label | string | nullable（「自名義」「母名義」等の表示ラベル） | [確定] |

### fc_memberships（名義＝FC会員資格）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| user_id | bigint | FK→users.id | [確定] |
| person_id | bigint | FK→persons.id | [確定] |
| group_id | bigint | FK→identity_groups.id | [確定] |
| artist_name | string | 担当アーティスト名 | [確定] |
| club_name | string | nullable（FC名） | [既定] |
| member_no | string | nullable（会員番号・コピー対象） | [確定] |
| login_id | string | 🔒 nullable（ログインID/メアド・コピー対象） | [確定] |
| password | string | 🔒 nullable（伏字＋コピー） | [確定] |
| joined_month | string | nullable（入会月 YYYY-MM） | [確定] |
| renewal_cycle | string | nullable（更新期間の記述） | [確定] |
| oshi_color | string | nullable（名義の担当色・UIの唯一の有彩色） | [既定] |

### venues（共有マスタ・全ユーザー共同蓄積 / user_idスコープの例外）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| name | string | | [確定] |
| address | string | nullable | [既定] |
| nearest_station | string | nullable | [既定] |
| capacity | integer | nullable | [既定] |
| created_by | bigint | nullable, FK→users.id（初回登録者・監査用） | [既定] |

### venue_notes（会場の個人メモ・ユーザー分離）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| user_id | bigint | FK→users.id | [確定] |
| venue_id | bigint | FK→venues.id | [確定] |
| lodging | string | nullable（定宿・ホテルエリア） | [確定] |
| transport_cost | string | nullable（交通費目安） | [確定] |
| memo | text | nullable | [確定] |
| (user_id, venue_id) | | unique | [確定] |

### attendances（参戦・主役）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| user_id | bigint | FK→users.id | [確定] |
| venue_id | bigint | nullable, FK→venues.id | [確定] |
| event_name | string | 公演名 | [確定] |
| event_date | date | | [確定] |
| open_time / start_time | time | nullable | [既定] |
| seat_raw | string | nullable（チケット表記そのまま） | [確定] |
| seat_block / seat_row / seat_number | string | nullable（任意の構造化・将来の座席図用） | [既定] |
| status | string | `in:applied,planned,attended,skipped`。default `attended` | [確定] |
| companion | string | nullable（同行者） | [既定] |
| memo | text | nullable | [確定] |

### attendance_identity（pivot・申込/当選の関節）
| カラム | 型 | 制約 | ステータス |
|---|---|---|---|
| id | bigint | PK | [確定] |
| attendance_id | bigint | FK→attendances.id | [確定] |
| fc_membership_id | bigint | FK→fc_memberships.id | [確定] |
| result | string | `in:pending,won,lost`。default `pending` | [確定] |
| ticket_count | integer | default 1（申込枚数） | [既定] |

## 5. ロジック・処理フロー

### 5-1. 参戦登録（S1・30秒導線）
入力: 日付・公演名・会場・座席raw・名義（複数選択可）・status
1. 会場名は既存venuesを部分一致サジェスト。ヒットなければ新規venue作成（created_by=自分）。
2. attendances作成（user_id=認証ユーザー）。
3. 選択名義ごとに attendance_identity 作成（result既定=pending）。
4. 参戦記録タイムラインへリダイレクト。

### 5-2. 年齢の自動計算
persons.birth_date から**PHP側で**計算（DB関数不使用）。誕生日未到来を考慮した満年齢。null なら非表示。

### 5-3. 当選率集計（S6）
名義詳細で該当 fc_membership の attendance_identity を集計:
申込数=該当pivot件数 / 当選数=result='won'件数 / 当選率=当選数÷申込数（**申込0件は「—」表示・ゼロ除算しない**）。

### 5-4. 招待→登録（S8）
1. `/invitations` で発行 → code生成、issued_by=自分。
2. `/register/{code}`: codeが存在・未使用・未失効なら登録フォーム、そうでなければ拒否。
3. 登録成功時: invitationのused_by/used_atを埋め、users.invited_by=issued_by。

### 5-5. パスワード・コピー（S4）
画面には `••••••••`。コピーボタン押下で `navigator.clipboard.writeText()` に**復号済み平文**を渡す。平文はDOMのテキストとして描画しない。会員番号・login_idも同機構。

## 6. バリデーション

| 項目 | ルール | エラー時 | ステータス |
|---|---|---|---|
| event_name | required, max:255 | 「公演名を入力してください」 | [確定] |
| event_date | required, date | 「日付を入力してください」 | [確定] |
| status | in:applied,planned,attended,skipped | 不正値は拒否 | [確定] |
| result(pivot) | in:pending,won,lost | 同上 | [確定] |
| identity_group.name | required, max:50 | 「グループ名を入力してください」 | [確定] |
| person.birth_date | nullable, date, 過去日 | 未来日は拒否 | [既定] |
| invitation.code | 存在・未使用・未失効 | 「この招待は使用できません」 | [確定] |
| venue_note (user,venue) | 一意 | 既存あれば更新（upsert） | [確定] |

## 7. エッジケース

CLAUDE.md 品質規約により、算出式の境界値・エッジケースは**必ずテスト化**する。

| ケース | 期待挙動 | ステータス |
|---|---|---|
| 会場サジェストで同名会場が複数 | 住所付きで候補表示し選択させる。無選択なら新規作成 | [既定] |
| 名義を選ばず参戦登録 | 許可（一般参戦）。pivotは作らない | [既定] |
| statusがskippedの参戦 | 積み上げ「今年の参戦」にカウントしない。タイムラインには表示（グレー表現） | [既定] |
| 同一公演に同一名義で複数口申込 | pivotを複数行許可（ticket_countで枚数区別も可） | [既定] |
| **グループ削除時に配下名義が残存** | **[未決:E1]。実装禁止。プレースホルダとして「削除不可」で仮置きしQUESTIONS.mdに記録** | [未決] |
| 他ユーザーのvenue_note/attendanceへの直アクセス | 403（user_idスコープで弾く）。**テスト化必須** | [確定] |
| birth_dateがうるう年2/29 | 平年は3/1到来で加齢とみなす（標準的満年齢計算）。**テスト化** | [既定] |
| クリップボードAPI非対応環境 | フォールバックで一時textareaコピー。不可なら伏字解除トグルを最終手段 | [既定] |
| 招待コードの同時多重使用 | 最初のトランザクションのみ成功、以降「使用済み」。DB一意制約＋used_atで担保。**テスト化必須** | [確定] |

## 8. 未決事項（実装禁止）

| No | 内容 | 選択肢（決定後にv1.0確定） |
|---|---|---|
| E1 | グループ削除時に配下名義が残っている場合の挙動 | A: 削除拒否（先に名義移動を促す・**片倉/Claude推奨**） / B: 「未分類」グループへ退避してから削除 / C: 名義ごと連鎖削除（危険・非推奨） |

**CCへの扱い**: E1決定前は「グループ削除機能を実装しない（削除ボタン無効 or 非表示）」で仮置きし QUESTIONS.md に記録。E1以外の S1〜S8 は全て実装着手可。

## 9. 分野間整合性検査（デザイン×データ×心理を統合）

| ペア | 照合質問 | 結果 | 収束 |
|---|---|---|---|
| デザイン×データ | 当落画面（公演内に名義行＋当落）は attendance_identity.result で表現可能か | YES | — |
| デザイン×データ | 名義カードの更新バッジは renewal_cycle から算出可能か | 要検証 | renewal_cycle自由記述だと日数算出不可。**MVPはテキスト表示に留め日数カウントダウンは対象外** |
| デザイン×データ | プライバシー文言「端末内保存」はサーバー保存構成と一致するか | NO | **文言を「名義情報は暗号化してサーバーに保存されます。他のユーザーからは見えません」に修正（片倉合意済み）** |
| データ×心理 | seat_raw必須化は入力負担にならないか | NO | **seat_rawはnullable。空でも保存可。構造化は完全任意** |
| デザイン×心理 | 名義画面のFAB非表示は「機密画面に記録動線を混ぜない」心理と一致するか | YES | モックJS実装と一致 |
| 心理×データ | 申込/当選を手入力させない判断は「二重管理をやめたい」動機と一致するか | YES | — |

### 実現可能性ゲート
| 機能 | 判定 |
|---|---|
| パスワードのコピー（平文非表示） | 実装可能（navigator.clipboard） |
| 会場共有マスタ＋個人メモ分離 | 実装可能（2テーブル） |
| 更新日数カウントダウン | 要検証（renewal_cycle構造化前提・MVP対象外） |
| 座席図ビジュアル | 対象外（会場座席データ資産が必要・スコープ外） |

## 変更履歴
- v1.0 (2026-07-07): 初版。CLAUDE.md（恒久ルール）と整合。未決1件(E1)。E1以外は着手可。
