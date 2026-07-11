# REPORT.md — 現場手帖 v2.7（2026-07-12）

> 検証はすべて **PHP 8.2.32** で実行。
> v2.7: cc_instructions_v2.7.md / spec v2.6 / security_requirements_v1.1 / security_criteria_v1.1

---

## T0. 現状調査（コード変更禁止）

### 1. JSON手動アップロード窓口の実装状況

**入口の画面・ルート名・コントローラ:**

| 経路 | ルート名 | コントローラ | メソッド | 画面 |
|---|---|---|---|---|
| GET /events/import | `events.import` | EventController::importForm() | — | `events/import.blade.php`（タブ切替: AI解析 / JSONアップロード） |
| POST /events/import/parse | `events.import.parse` | EventController::importParse() | AI解析→確認画面 | `events/import-confirm.blade.php` |
| POST /events/import | `events.import.store` | EventController::importStore() | AI確認画面→DB登録 | — |
| POST /events/import/json | `events.import.json` | EventController::importJson() | JSONパース→確認画面 | `events/import-confirm-json.blade.php` |
| POST /events/import/json/store | `events.import.json.store` | EventController::importJsonStore() | JSON確認画面→DB登録 | — |

**セットリスト側の入口（別経路）:**

| 経路 | ルート名 | コントローラ | メソッド |
|---|---|---|---|
| POST /tours/{tour}/setlists/json | `setlists.json-import` | SetlistController::jsonImport() | JSON貼り付け→確認 |
| POST /tours/{tour}/setlists/ai-parse | `setlists.ai-parse` | SetlistController::aiParse() | AI解析→確認 |
| POST /tours/{tour}/setlists/bulk | `setlists.bulk-store` | SetlistController::bulkStore() | 一括登録 |

FormRequest: なし（全てコントローラ内inline）

**受け付けているJSONの種別:**
- **events JSON のみ**。スキーマ: `{"tour":"ツアー名","events":[{"event_label":null,"event_date":"YYYY-MM-DD","start_time":"HH:MM","venue":"会場名"}],"deadlines":[...]}`
- setlist JSONは **受け付けていない**（後述）

**アップロード→確認画面→登録の流れ:**
- JSON経路: importJson() でJSONパース→import-confirm-json.blade.phpで確認表示→importJsonStore()でDB登録。**確認画面を経由する正しい流れ**。直INSERTなし
- AI経路: importParse()でLLM解析→import-confirm.blade.phpで確認表示→importStore()でDB登録。同様に確認画面経由

### 2. JSONのバリデーション実装の有無

**importJson() (パース段階):**
- JSON構文検証: `json_decode`の結果がarrayかチェック (`EventController.php:187, 199`)
- スキーマ検証: `$decoded['events']`の存在チェック**のみ** (`EventController.php:188, 209`)
- キーの型・必須・不正値の検証: **なし**（パース段階では行っていない）

**importJsonStore() (登録段階):**
- Laravel Validatorでバリデーション (`EventController.php:219-232`):
  - `tours.*.tour_name` → required, string, max:255
  - `tours.*.events.*.event_date` → **nullable**, date（required ではない）
  - `tours.*.events.*.start_time` → nullable, date_format:H:i
  - `tours.*.events.*.venue_name` → nullable, string, max:255
  - FK検証（event_idの存在チェック）: 該当なし（events JSONにはevent_idがないため）

**不正JSONの実際の挙動（コード追跡による検証）:**

```
Test 1 - セットリストJSON {"items":[...]}:
  json_decode → is_array: YES
  isset($decoded['events']): NO
  → 「JSONの形式が正しくありません。各要素に "events" 配列が必要です」で弾かれる（500ではない）

Test 2 - 壊れたJSON "{broken json":
  json_decode → NULL
  is_array: NO
  → 「JSONの形式が正しくありません」で弾かれる（500ではない）

Test 3 - eventsキーなし {"tour":"test"}:
  isset($decoded['events']): NO
  → 「JSONの形式が正しくありません。各要素に "events" 配列が必要です」で弾かれる

Test 4 - events空配列 {"tour":"test","events":[]}:
  → 確認画面に到達するが0件表示
```

### 3. セットリストJSONが弾かれる件

**原因: `EventController::importJson()` の L209 で `isset($t['events'])` をチェック**

```php
// EventController.php:208-211
foreach ($tours as $t) {
    if (! isset($t['events']) || ! is_array($t['events'])) {
        return back()->with('error', 'JSONの形式が正しくありません。各要素に "events" 配列が必要です');
    }
}
```

セットリストJSON `{"items":[{"order":1,"title":"曲1","note":null}]}` には `events` キーが存在しないため、上記チェックで弾かれる。

セットリスト側にはJSON入力経路が**別途存在する**: `SetlistController::jsonImport()` (POST /tours/{tour}/setlists/json)。ただしこれは：
- ツアーを既に指定した状態でアクセスする必要がある（URLに`{tour}`を含む）
- `events/import` の統合画面からは到達できない
- セットリスト画面 (`setlists/show.blade.php`) の「JSON貼り付け」タブからのみアクセス可能

**結論: events/importのJSONアップロード窓口にsetlist JSONを投入すると弾かれる。別入口を使う必要があるが、統合されていない。**

### 4. EventImportParser / LotImportService の利用状況

**EventImportParser:**
- **削除済み**。`app/Services/` 配下にファイルなし
- `grep -rn 'EventImportParser' --include='*.php'` → **0件**（テストも含め完全に除去済み）
- REPORT.md v2.1で「§6 EventImportParser削除 ✅ 削除済み」と記録

**LotImportService:**
- `app/Services/LotImportService.php` に**物理的に存在するがデッドコード**
- ルート・コントローラからの参照: **なし**（grep結果で自身のクラス定義のみ）
- テスト: `tests/Unit/LotImportParserTest.php` が存在し、**6テストが全通過**（テスト自体は動作する）
- 実際にアプリからは呼ばれていない

**Ollama/Geminiドライバの残存:**
- `app/Services/Llm/OllamaLlmService.php` — 残存
- `app/Services/Llm/GeminiLlmService.php` — 残存
- `app/Providers/AppServiceProvider.php` — 3ドライバのswitch分岐が残存
- `config/llm.php` — ollama/gemini設定が残存、デフォルトドライバが `'ollama'`

### git diff（コード変更なし確認）
```
git diff --stat:
 .claude/settings.json |  11 ++-   ← ユーザーによる設定変更（CC作業ではない）
 docs/spec.md          | 210 +++   ← ユーザーが配置した新仕様書（CC作業ではない）
```
CC側のコード変更: **0行**

---

## 検証ライン判定表（T0時点・ベースライン）

### V1: `php artisan migrate --force` — 未実行（T0はコード変更なし）

### V2: `/up` HTTP 200 — 未実行

### V3: `php artisan test` — YES（123テスト・308アサーション）
```
Tests:    123 passed (308 assertions)
Duration: 1.65s
```
