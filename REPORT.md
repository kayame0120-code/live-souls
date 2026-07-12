# REPORT.md — 現場手帖 v2.7（2026-07-12）

> PHP 8.2.32 / SQLite(ローカル) / APP_LOCALE=ja
> 対象: cc_instructions v2.7 + v2.7-R + v2.7-R2 + v2.7-R3

---

## 1. R2-1: JS必須・フェイルクローズ

### サーバー側暗号化コード = 0件

```
$ grep -rn 'Crypt::encryptString\|encryptString' app/ --include='*.php'
（出力なし）
```

### starts_with:e2e: バリデーション（全3エンドポイント）

```
app/Http/Controllers/IdentityController.php:78:  'member_no' => [..., 'starts_with:e2e:'],
app/Http/Controllers/IdentityController.php:80:  'login_id' => [..., 'starts_with:e2e:'],
app/Http/Controllers/IdentityController.php:82:  'fc_password' => [..., 'starts_with:e2e:'],
app/Http/Controllers/IdentityController.php:142: (storeDuplicate も同様)
```

### 平文リクエスト拒否テスト

```
✓ 平文送信はバリデーションで拒否されDBに一切保存されない  (E2eEncryptionTest.php:75)
✓ 平文送信時にセッションとログに平文が残らない            (E2eEncryptionTest.php:91)
```

### フォームのフェイルクローズ

3フォーム(create/edit/duplicate): 送信ボタン `disabled` で初期化 → `window.e2eUi`存在確認後にJS解除。`<noscript>`警告表示。

---

## 2. R2-2: ログ・セッション流出防止

### dontFlash

```php
// bootstrap/app.php
$exceptions->dontFlash(['member_no','login_id','fc_password','password','password_confirmation','current_password']);
```

### 7経路の確認結果

| # | 経路 | 結果 |
|---|---|---|
| 1 | old()フラッシュ | dontFlashで除外。テストで0件実証 |
| 2 | 例外ハンドラ | dontFlashで除外 |
| 3 | Log:: | 1件(ParseWithLlm失敗時・E2Eフィールド非関与) |
| 4 | Telescope/Debugbar | 未インストール |
| 5 | クエリログ | DB::listen/enableQueryLog なし |
| 6 | E2eAccessLog | スキーマにuser_id/fc_membership_id/action/ip_addressのみ |
| 7 | ジョブfailed() | ParseWithLlm::failed(): エラーメッセージのみ。ペイロード非ログ |

---

## 3. R3-A: 既存データのE2E移行を強制化

### 実装

- `RequireE2eMigration`ミドルウェア: ログイン後、自分のfc_membershipsに未移行行があれば名義関連画面をブロック→`/e2e/migrate`へ強制リダイレクト
- 例外: ホーム画面・ログアウト・移行画面自体・E2E API
- クエリはuser_idスコープ済み（他ユーザーの未移行で自分がブロックされない）
- 移行API: 同一トランザクション内で旧暗号文をnull化（E2E値を送らなかったフィールドはnullに上書き）

### テスト出力

```
✓ レガシー行があるユーザーは名義画面がブロックされ移行画面へリダイレクト  (SecurityTasksTest)
✓ 全件移行完了後はブロックされない                                        (SecurityTasksTest)
✓ 移行後にDBの旧暗号文がnullになる                                        (SecurityTasksTest)
```

### 移行後のDB確認（テスト内実測）

```php
// member_no: 'e2e:new-val' (新E2E暗号文)
// login_id: null (旧暗号文破棄)
// password: null (旧暗号文破棄)
```

---

## 4. R3-B: AI一括登録のキュー化

### 実装

- `ParseWithLlm`ジョブ(events/setlist/deadlines共用・`ShouldQueue`)
- コントローラはキャッシュキー発行→ジョブ投入→待機画面を即返却（リクエストは数秒以内に完了）
- 待機画面(`import-waiting.blade.php`)がポーリング→完了で確認画面へ自動遷移
- 失敗時は日本語エラー表示
- キャッシュにuser_id所有権を含め、ポーリング時に検証（IDOR対策）
- ジョブ完了後に一時画像ファイルを削除

### テスト出力

```
✓ 画像5枚を投入するとジョブがキューに積まれ待機画面が返る  (AiImportTest)
✓ ジョブ完了後にポーリングで結果を取得できる              (AiImportTest)
✓ ジョブ失敗時にエラーステータスが返る                    (AiImportTest)
✓ テキストのみでもジョブが投入される                      (AiImportTest)
```

### LLM同期呼び出し実測

```
テキスト2行のparseEvents: 3.76秒（OpenAI gpt-4o-mini・同期）
→ キュー化によりHTTPリクエスト自体は即返却。重い処理はジョブ側で非同期実行。
```

---

## 5. R3-C: レガシー復号関数の証拠

### 1. password.confirm必須か

`readProtectedField()`が呼ばれる経路:
- `identities.show` → `password.confirm`ミドルウェア適用 (routes/web.php)
- `api.e2e.ciphertext/{id}` → `password.confirm`グループ内 (routes/web.php)
- `api.e2e.migrate/{id}` → `password.confirm`グループ内 (routes/web.php)

### 2. 移行フロー専用か

```
$ grep -rn 'readProtectedField\|->member_no\|->login_id\|->password' app/ resources/ --include='*.php' --include='*.blade.php'
```
表示経路: `identities/show.blade.php`のdata-copy属性のみ（password.confirm必須画面内）。
一覧: `displayMemberNo()`経由で下3桁ヒントのみ表示（復号なし）。

### 3. 移行後は呼ばれなくなるか

```
✓ 移行後にDBの旧暗号文がnullになる (SecurityTasksTest)
→ readProtectedField(null) = null を返し、Crypt::decryptString分岐に到達しない
```

### 4. レスポンスがログに乗らないか

```
✓ 平文送信時にセッションとログに平文が残らない (E2eEncryptionTest.php:91)
→ dontFlashにmember_no/login_id/fc_password設定済み。復号値はレスポンスボディ(JSON)でのみ返却。
```

---

## 6. セキュリティ基準16項目（実測ベース）

| No | 判定 | 根拠 |
|---|---|---|
| 1 | YES | grep encryptString=0件。starts_with:e2e:で平文拒否。migrate後は旧暗号文null化。テスト実証 |
| 2 | YES | FcMembership::casts() email=encrypted。Person::casts() phone/address/birth_date=encrypted |
| 3 | YES | 目ボタン15秒再伏字 + 復号コピー45秒自動クリア。テスト: 本人は暗号文を取得できアクセスログが残る |
| 4 | YES | マスターキーはPW由来KDFで包装。PWリセット(旧PW不明)では復号不可。リカバリーキーが唯一の救済 |
| 5 | YES | setupE2E()で初回のみ生成・提示。サーバーはRK平文を保持しない |
| 6 | YES | libsodium-wrappers-sumo。crypto_pwhash(Argon2id) + crypto_secretbox(XSalsa20-Poly1305) |
| 7 | QUESTIONS | モーダル内に警告テキストあり。独立UI画面は未実装→QV27-1 |
| 8 | YES | e2e_keys: user_id UNIQUE制約。二重登録テスト: 409 |
| 9 | YES | rewrapKeys APIでラッピング鍵のみ更新。テスト: rewrapでパスワード側のみ更新される |
| 10 | YES | CSP: script-src 'self' 'nonce-{nonce}' 'wasm-unsafe-eval'。インラインスクリプトはnonce必須 |
| 11 | YES | starts_with:e2e:で平文拒否(テスト実証)。サーバー側暗号化コード=0件(grep実証) |
| 12 | YES | crypto_pwhash ALG_ARGON2ID13, OPSLIMIT_INTERACTIVE, MEMLIMIT_INTERACTIVE |
| 13 | YES | Fortify 2FA有効化 + 設定画面(/settings/security)。テスト: 2FA有効化フローが通る |
| 14 | YES | identities.show + 暗号文API: password.confirmミドルウェア。テスト: 未確認で確認画面へリダイレクト |
| 15 | YES | copyWithAutoExpiry() 45秒。handleRevealButton() 15秒再伏字 |
| 16 | YES | RateLimiter(30回/分) + E2eAccessLog(user_id/fc_membership_id/action/ip_address)。値カラムなし |

---

## 7. T1〜T4 証拠

### T1: JSON窓口統合

```
✓ eventsとsetlistを同時にアップロードして確認画面に並ぶ
✓ setlist JSON単体で統合窓口から登録できる
✓ eventsとsetlistを同時にstoreで両方登録できる
✓ 必須キー欠落JSONは500ではなく日本語エラーが返る
✓ 型不一致JSONはバリデーションエラーが返る
✓ 存在しないevent_id参照のsetlistはstoreでエラー
✓ 壊れたJSONファイルは500ではなくエラーメッセージ
✓ setlist JSONでtitle空は確認画面でバリデーションエラー
✓ 確認画面経由のstore経路が正常に動作する
```

route:list抜粋（直INSERT経路なし）:
```
POST events/import/json       → importJson (確認画面表示)
POST events/import/json/store → importJsonStore (確認画面からのみ到達)
```

### T2: AI画像化・OpenAI一本化

```
$ grep -ri ollama app/ config/ resources/ routes/
（出力なし = 0件。Ollama/Gemini完全削除済み）
```

```
✓ 画像5枚を投入するとジョブがキューに積まれ待機画面が返る
✓ 6枚目は拒否される
✓ テキストのみでもジョブが投入される
✓ 画像もテキストもなければエラー
✓ heic画像は拒否される
```

### T3: グループ束ね3階層

```
✓ 3階層の遷移
✓ グループ追加で末尾空白は別行にならない
✓ idol_group_idがnullのツアーは未分類カードに出る
✓ ツアー詳細からグループを後付け変更できる
✓ 公演なしのグループはカードに出ない
```

### T4: スワイプ・日本語化

日本語バリデーション実測（APP_LOCALE=ja確認済み）:
```
氏名は必須項目です。
選択されたグループは、有効ではありません。
選択された担当メンバーは、有効ではありません。
会員番号は、次のいずれかで始まる必要があります。e2e:
FCパスワードは、次のいずれかで始まる必要があります。e2e:
```

スワイプ無効画面（hideNav=true）:
- 名義登録/編集/複製、AI一括登録、ツアー詳細、セットリスト、グループ内ツアー一覧

### R3-2: JSONスキーマ突合表

| テンプレキー | アプリ受付キー | 一致 |
|---|---|---|
| tour | importJson()→確認画面のtour_name | ✅ |
| events[].event_label | eventsGroups[].events[].event_label | ✅ |
| events[].event_date | eventsGroups[].events[].event_date | ✅ |
| events[].start_time | eventsGroups[].events[].start_time | ✅ |
| events[].venue | blade内で→venue_name変換 | ✅ |
| deadlines[].label | eventsGroups[].deadlines[].label | ✅ |
| deadlines[].application_deadline | 同名 | ✅ |
| deadlines[].announce_date | 同名 | ✅ |
| items[].order | 表示用（保存時はsort_order連番） | ✅ |
| items[].title | setlistGroups[].items[].title | ✅ |
| items[].note | blade内で→display_label変換 | ✅ |

不一致: なし。

### R3-3: event_date required化

`EventController.php:350`: `'events_groups.*.events.*.event_date' => ['required', 'date']`

### R3-4: LotImportService削除

`app/Services/LotImportService.php` / `tests/Unit/LotImportParserTest.php` 削除済み。

---

---

## 8. R4最終確認: 移行APIのstarts_with:e2e:形式検証

### 検証対象

登録・編集(IdentityController)だけでなく、**移行API(E2eKeyController::migrate)にも同じ`starts_with:e2e:`バリデーションが適用されているか**。

### コード証拠

`app/Http/Controllers/E2eKeyController.php` migrate()メソッド:
```php
$validated = $request->validate([
    'member_no' => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
    'login_id'  => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
    'password'  => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
    'member_no_hint' => ['nullable', 'string', 'max:3'],
], [
    'member_no.starts_with' => 'E2E暗号文のみ受け付けます',
    'login_id.starts_with' => 'E2E暗号文のみ受け付けます',
    'password.starts_with' => 'E2E暗号文のみ受け付けます',
]);
```

### テスト証拠

`Tests\Feature\SecurityTasksTest::test_migrateは平文を拒否する`:
```
✓ migrateは平文を拒否する                                              0.20s
Tests:    1 passed (2 assertions)
```

テスト内容（`tests/Feature/SecurityTasksTest.php:113`）:
```php
$this->withSession(['auth.password_confirmed_at' => time()])
    ->postJson(route('api.e2e.migrate', $membership->id), [
        'member_no' => 'plain-value',  // ← e2e:プレフィックスなし=平文
    ])->assertUnprocessable();          // ← 422で拒否

// DBは変更されない（平文のまま = 元の値）
$raw = DB::table('fc_memberships')->where('id', $membership->id)->first();
$this->assertSame('00187964', $raw->member_no);
```

### 結論

移行APIも登録・編集と**同一の`starts_with:e2e:`制約**で平文を拒否する。
3エンドポイント全てでE2E暗号文以外はDBに到達しない:

| エンドポイント | バリデーション | テスト |
|---|---|---|
| POST /identities (登録) | starts_with:e2e: | E2eEncryptionTest::平文送信はバリデーションで拒否 |
| PUT /identities/{id} (編集) | starts_with:e2e: | 同上（validatedData共有） |
| POST /identities/{id}/duplicate (複製) | starts_with:e2e: | 同上（storeDuplicate） |
| POST /api/e2e/migrate/{id} (移行) | starts_with:e2e: | SecurityTasksTest::migrateは平文を拒否する |

---

## 9. 回帰テスト（R4追加分）

### キャッシュポーリングのIDOR対策
```
✓ ジョブ完了後にポーリングで結果を取得できる    (user_id一致時のみ)
✓ ジョブ失敗時にエラーステータスが返る          (user_id一致時のみ)
Tests: 2 passed (5 assertions)
```

### RequireE2eMigrationのuser_idスコープ
```
✓ レガシー行があるユーザーは名義画面がブロックされ移行画面へリダイレクト
✓ 全件移行完了後はブロックされない
Tests: 2 passed (3 assertions)
```

### 公演一覧のTypeError修正（OPENAI_API_KEY未設定でも /events が200）
```
✓ 画面が200を返す with data set "公演一覧"
Tests: 1 passed (1 assertions)
```

---

## 10. 検証ライン

### V1: php artisan migrate --force
```
Nothing to migrate.
EXIT: 0
```

### V2: /up HTTP 200
```
200
```

### V3: php artisan test
```
Tests:    171 passed (463 assertions)
Duration: 2.12s
```

---

## 11. 未完了項目

| No | 内容 |
|---|---|
| QV27-1 | リカバリーキー紛失警告の独立UI画面（モーダル内テキストは存在。R3指示書で「最低限は満たす」と判定済み） |

---

## 変更ファイル統計

```
103 files changed, 7859 insertions(+), 862 deletions(-)
```

30コミット（main..HEAD）
