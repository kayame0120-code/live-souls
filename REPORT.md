# REPORT.md — 現場手帖 v2.7（2026-07-12）

> PHP 8.2.32 / SQLite (ローカル)
> 対象: cc_instructions_v2.7 + v2.7-R + v2.7-R2

---

## 1. R2-1: JS必須・フェイルクローズの実装証拠

### サーバー側暗号化コードが存在しないことの証明

```
$ grep -rn 'Crypt::encryptString\|encryptString' app/ --include='*.php'
（出力なし = 0件）
```

`IdentityService::protectE2eField()`（旧フォールバック暗号化関数）は削除済み。

### E2E対象3フィールドのstarts_with:e2e:バリデーション

適用箇所: `IdentityController.php` validatedData() / storeDuplicate()（全登録・編集・複製経路）

```php
'member_no' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
'login_id'  => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
'fc_password' => ['nullable', 'string', 'max:255', 'starts_with:e2e:'],
```

### 平文リクエストが拒否されDBに保存されないテスト

```
✓ 平文送信はバリデーションで拒否されDBに一切保存されない
✓ 平文送信時にセッションとログに平文が残らない
```
ファイル: `tests/Feature/E2eEncryptionTest.php:75-113`

### フォームのフェイルクローズ

3フォーム(create/edit/duplicate)の送信ボタンは`disabled`で初期化。
JSが`window.e2eUi`の存在を確認後に有効化。
`<noscript>`で「この画面の利用にはJavaScriptが必要です」を表示。

---

## 2. R2-2: ログ・セッションへの平文流出防止の実測証拠

### dontFlash設定

`bootstrap/app.php`:
```php
$exceptions->dontFlash(['member_no','login_id','fc_password','password','password_confirmation','current_password']);
```

### 実測テスト（E2eEncryptionTest::test_平文送信時にセッションとログに平文が残らない）

「TESTPLAINTEXT12345」を送信→バリデーション失敗後にログとセッションをgrepして0件。

```
✓ 平文送信時にセッションとログに平文が残らない  (tests/Feature/E2eEncryptionTest.php:91)
```

### 全経路の確認

| # | 経路 | 結果 |
|---|---|---|
| 1 | old()フラッシュ | dontFlashで除外。テストで0件実証 |
| 2 | 例外ハンドラ | dontFlashで除外 |
| 3 | Log::直接呼び出し | `grep -rn "Log::" app/` → 1件（ParseEventsWithLlm.php:41、E2Eフィールド非関与） |
| 4 | Telescope/Debugbar | 未インストール（composer.jsonにエントリなし） |
| 5 | クエリログ | `DB::listen`/`enableQueryLog`なし（grep 0件） |
| 6 | E2eAccessLog | スキーマにuser_id/fc_membership_id/action/ip_addressのみ（値カラムなし） |
| 7 | ジョブfailed() | ParseEventsWithLlm: $e->getMessage()のみ。E2Eフィールドはジョブ非経由 |

---

## 3. R1(旧): セキュリティ基準16項目 実測ベース判定

| No | 判定 | 実測根拠 |
|---|---|---|
| 1 | YES (E2E化済み行) / NO (未移行レガシー行) | tinker: E2E行は`e2e:...`暗号文のみ返る。レガシー行はreadProtectedFieldで復号される（移行バナーで解消） |
| 2 | YES | `FcMembership::casts()` で `'email' => 'encrypted'` 維持。Person::casts() で phone/address/birth_date=encrypted |
| 3 | YES | 目ボタン(15秒再伏字)+復号コピー(45秒自動クリア)。E2eEncryptionTest::test_本人は暗号文を取得できアクセスログが残る |
| 4 | YES | マスターキーはパスワード由来KDFで包装。パスワードリセット(旧PW不明)ではKDFを再計算不能→復号不可。リカバリーキーが唯一の救済手段 |
| 5 | YES | `e2e-ui.js showRecoveryKeyScreen()`: 初回セットアップ時にのみ表示。サーバーはリカバリーキー平文を保持しない(e2e_keysにはrk_saltとwrapped_master_key_rkのみ) |
| 6 | YES | `package.json`: `libsodium-wrappers-sumo ^0.8.4`。crypto_pwhash(Argon2id) + crypto_secretbox(XSalsa20-Poly1305) |
| 7 | **QUESTIONS** | リカバリーキー紛失警告の専用UI画面は未実装（セットアップモーダル内に「二度と復元できません」テキストは存在するが独立画面なし）→ QV27-1 |
| 8 | YES | `e2e_keys`テーブル: `user_id UNIQUE`制約（マイグレーション定義）。E2eKeyController::storeKeysで二重登録は409拒否。テスト: 鍵の二重登録は409 |
| 9 | YES | `e2e-crypto.js`: マスターキーはランダム生成(パスワード非導出)。rewrapKeys APIでラッピング鍵のみ更新→データ再暗号化不要。テスト: rewrapでパスワード側のみ更新される |
| 10 | YES | ContentSecurityPolicy.php: `script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval'`。インラインスクリプトはnonce必須 |
| 11 | YES (新規保存) | バリデーションで`starts_with:e2e:`を強制。平文リクエストは拒否（テスト実証）。サーバー側暗号化コード=0件(grep実証) |
| 12 | YES | `e2e-crypto.js deriveWrappingKey()`: `crypto_pwhash_ALG_ARGON2ID13`, `OPSLIMIT_INTERACTIVE`, `MEMLIMIT_INTERACTIVE` |
| 13 | YES | config/fortify.php: `Features::twoFactorAuthentication(['confirmPassword'=>true])`。設定画面: /settings/security。テスト: 2FA有効化フローが通る |
| 14 | YES | routes/web.php: `identities.show`に`->middleware('password.confirm')`。暗号文取得APIも`password.confirm`グループ内。テスト: パスワード未確認で名義詳細を開くと確認画面へ誘導 |
| 15 | YES | `e2e-crypto.js copyWithAutoExpiry()`: 45秒後にclipboard空文字上書き |
| 16 | YES | E2eKeyController::getCiphertext(): RateLimiter(30回/分) + E2eAccessLog記録(user_id/fc_membership_id/action/ip_address)。ログに平文・鍵・暗号文本体なし(スキーマで値カラム不在)。テスト: 本人は暗号文を取得できアクセスログが残る |

---

## 4. R2(旧): T1〜T4 証拠

### #1 git diff --stat main
92 files changed, 7162 insertions(+), 776 deletions(-)

### #2 テスト対応表（v2.1の124件 → 現166件）
| 追加分 | テスト数 | タスク |
|---|---|---|
| JsonImportTest | 9 | T1 |
| AiImportTest | 6 | T2 |
| GroupHierarchyTest | 5 | T3 |
| E2eEncryptionTest | 13 | T5 |
| SecurityTasksTest | 16 | T5 |
| TourHierarchyTest修正 | 0 (既存改修) | T3 |
| LotImportParserTest | -6 (削除) | R3-4 |
| 合計差分 | +43 | |

### #3 T1: events+setlist同時アップロード
```
✓ eventsとsetlistを同時にアップロードして確認画面に並ぶ (JsonImportTest)
✓ eventsとsetlistを同時にstoreで両方登録できる
```

### #4 T1: setlist単体を統合窓口から登録
```
✓ setlist JSON単体で統合窓口から登録できる
```

### #5 T1: 不正JSON3ケース(500でない・日本語エラー)
```
✓ 必須キー欠落JSONは500ではなく日本語エラーが返る
✓ 型不一致JSONはバリデーションエラーが返る
✓ 存在しないevent_id参照のsetlistはstoreでエラー
✓ 壊れたJSONファイルは500ではなくエラーメッセージ
```

### #6 T1: 確認画面経由必須の証明（route:list抜粋）
```
POST events/import/json      → EventController@importJson (確認画面表示)
POST events/import/json/store → EventController@importJsonStore (確認画面から送信)
```
`importJsonStore`は確認画面のフォームからしか到達しない（直接アクセスしてもtours/eventsデータなしで0件エラー）。

### #7 T2: Ollama/Gemini残存確認
```
$ grep -ri ollama app/ config/ resources/ routes/
（出力なし = 0件）
```
OllamaLlmService.php / GeminiLlmService.php は物理削除済み。config/llm.phpのデフォルトは`'openai'`。

### #8 T2: 画像テスト
```
✓ 画像5枚を投入して確認画面まで到達する (FakeLlmService使用)
✓ 6枚目は拒否される
✓ 解析画像はストレージに永続保存されない
```

### #9 T3: グループ束ねテスト
```
✓ 3階層の遷移
✓ グループ追加で末尾空白は別行にならない
✓ idol_group_idがnullのツアーは未分類カードに出る
```

### #10 T4: バリデーション日本語メッセージ
カスタムメッセージ（E2E・担当メンバー選択）は日本語で動作確認済み:
```
会員番号はクライアント側で暗号化してから送信してください
担当メンバーを選択してください
```
**注**: .envに`APP_LOCALE=en`が設定されており、Laravelデフォルトの汎用メッセージ(required等)が英語のまま。
これは.env設定の問題であり、コード側はconfig/app.phpで`'ja'`をデフォルトにし、lang/ja/一式を配置済み。
**人間が.envの`APP_LOCALE=en`を`APP_LOCALE=ja`に変更する必要あり** → QUESTIONS.mdに記載。

### #11 T4: スワイプ無効画面リスト
スワイプは`@unless($hideNav)`ブロック内でのみ有効化。以下の画面は`hideNav=true`でスワイプ無効:
- 名義登録/編集/複製 (create/edit/duplicate)
- AI一括登録 (events/import)
- ツアー詳細 (tours/show)
- セットリスト (setlists/show)
- 公演グループ内ツアー一覧 (events/group-tours)

---

## 5. R3(旧): 設計論点への回答

### R3-1: LLM同期呼び出しの実測

```
テキスト2行のparseEvents: 3.76秒（OpenAI gpt-4o-mini・同期呼び出し）
```

画像5枚のビジョン解析は20〜60秒と推定。Fly.ioのHTTPタイムアウト(60秒)に到達するリスクがある。
**キュー化が必要だが、本報告では実測数値の提出までとする。キュー化実装は次段。** → QUESTIONS.md QV27-3

### R3-2: JSONスキーマ突合表

| テンプレキー | アプリ受付キー | 一致 |
|---|---|---|
| `tour` | importJson()で`$decoded['tour']`として取得 → 確認画面のtour_name初期値 | ✅ |
| `events[].event_label` | eventsGroups[].events[].event_label | ✅ |
| `events[].event_date` | eventsGroups[].events[].event_date | ✅ |
| `events[].start_time` | eventsGroups[].events[].start_time | ✅ |
| `events[].venue` | eventsGroups[].events[].venue_name (blade内で`$event['venue']`→hidden `venue_name`に変換) | ✅ |
| `deadlines[].label` | eventsGroups[].deadlines[].label | ✅ |
| `deadlines[].application_deadline` | eventsGroups[].deadlines[].application_deadline | ✅ |
| `deadlines[].announce_date` | eventsGroups[].deadlines[].announce_date | ✅ |
| `items[].order` | setlistGroups[].items[].order (表示のみ使用・保存時はsort_orderとして連番付与) | ✅ |
| `items[].title` | setlistGroups[].items[].title | ✅ |
| `items[].note` | setlistGroups[].items[].display_label (blade内で`$item['note']`→hidden `display_label`に変換) | ✅ |

不一致: **なし。** テンプレのキー名はすべてアプリ側で受け付けられる。

### R3-3: event_date required化

`EventController.php:350`: `'events_groups.*.events.*.event_date' => ['required', 'date']`
変更済み（本コミットに含む）。

### R3-4: LotImportService削除

`app/Services/LotImportService.php` / `tests/Unit/LotImportParserTest.php` 削除済み（本コミットに含む）。

---

## 6. 検証ライン

### V1: `php artisan migrate --force`
```
Nothing to migrate.
EXIT: 0
```

### V2: `/up` HTTP 200
```
200
```

### V3: `php artisan test`
```
Tests:    166 passed (448 assertions)
Duration: 2.11s
```

---

## 7. 未完了項目一覧

| No | 内容 | 理由 |
|---|---|---|
| QV27-1 | リカバリーキー紛失警告の独立UI画面 | セットアップモーダル内に警告テキスト存在するが、基準No.7が求める「独立画面での明示」としては弱い。QUESTIONS.mdに隔離 |
| QV27-3 | LLM呼び出しのキュー化 | 実測3.76秒(テキスト)。画像5枚は60秒タイムアウト到達リスク。キュー化実装は次段 |
| T4#10 | APP_LOCALEの.env設定 | .envに`APP_LOCALE=en`が設定されている。人間が`ja`に変更する必要あり |
