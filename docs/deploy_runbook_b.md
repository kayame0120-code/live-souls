# deploy_runbook_b.md — デプロイB: persons.phone/address E2E化 + 2FA confirm修正

> 片倉が単独で上から順に実行する。

## 0. ローカル検証完了（実施済み）

- V1: 45マイグレーション全DONE（DBスキーマ変更なし。release_commandは空振りで正常終了）
- V2: `/up` → `200`
- V3: `Tests: 184 passed (504 assertions)`
- V4: レガシーencrypted値のE2E移行→冪等性確認済み

## 1. バックアップ取得＋サイズ確認

pg_dumpはアプリイメージに存在しない。Postgres VM（`live-souls-db`）から実行する。

```bash
# アプリVMからDATABASE_URLを取得（パスワード確認用）
fly ssh console -C 'sh -c "echo \$DATABASE_URL"' --app live-souls
```

→ `postgres://live_souls:<パスワード>@live-souls-db.flycast:5432/live_souls?sslmode=disable` が出る。`<パスワード>` を以下のコマンドに使う。

```bash
# Postgres VMでpg_dump実行（<パスワード>を上の出力から置換）
fly ssh console -C "sh -c 'pg_dump \"postgres://live_souls:<パスワード>@live-souls-db.flycast:5432/live_souls?sslmode=disable\" --format=custom --file=/tmp/backup_before_deployB.dump'" --app live-souls-db
```

→ 出力なし＝成功。

```bash
# サイズ確認（サーバー側）
fly ssh console -C "ls -lh /tmp/backup_before_deployB.dump" --app live-souls-db
```

→ `99K` 程度のサイズが表示されること（0バイトでないこと）。

```bash
# ローカルにダウンロード
fly sftp get /tmp/backup_before_deployB.dump ./backup_before_deployB.dump --app live-souls-db

# サイズ確認（ローカル側）
ls -lh ./backup_before_deployB.dump
```

→ サーバー側と同じサイズであること。

## 2. fly.toml の release_command（確認のみ）

```toml
[deploy]
  release_command = 'php /var/www/html/artisan migrate --force'
```

デプロイBにはDBスキーマ変更なし。release_commandは `Nothing to migrate.` で正常終了する。

## 3. デプロイ

```bash
fly deploy
```

→ ログで release_command の完了を確認:

```bash
fly logs --app live-souls | grep -i migrate
```

→ `Nothing to migrate.` または `Main child exited normally with code: 0` が出れば成功。

## 4. 動作確認

### 4-1. /up ヘルスチェック

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://live-souls.fly.dev/up
```

→ `200`

### 4-2. 残存secret確認（F5同型）

```bash
set +H
fly ssh console --app live-souls -C 'php /var/www/html/artisan tinker --execute="echo json_encode(DB::select(\"SELECT id, two_factor_secret IS NOT NULL AS has_secret FROM users WHERE two_factor_secret IS NOT NULL AND two_factor_confirmed_at IS NULL\"));"'
```

→ `[]`（空配列）であること。該当行があれば以下で応急リセット:

```bash
fly ssh console --app live-souls -C 'php /var/www/html/artisan tinker --execute="DB::table(\"users\")->whereNotNull(\"two_factor_secret\")->whereNull(\"two_factor_confirmed_at\")->update([\"two_factor_secret\" => null, \"two_factor_recovery_codes\" => null]); echo \"Reset done\";"'
```

### 4-3. 片倉自身のアカウントで移行フローを1周

1. https://live-souls.fly.dev にログイン
2. 名義一覧（`/identities`）にアクセス → 移行バナーが表示されること（personsのphone/addressがレガシー形式のため）
3. 「すべてE2E暗号化する」をクリック → E2Eアンロックモーダルが表示される
4. ログインパスワードを入力 → リカバリーキー画面が表示される（初回の場合）→ リカバリーキーを**安全な場所に保管**
5. 移行が完了し「完了: ◯件の名義をE2E暗号化しました」と表示される
6. 名義詳細で住所・電話番号の👁ボタン → 復号された値が表示されること
7. 名義詳細でコピーボタン → 正しい値がコピーされること

### 4-4. 検証SQL（実行者自身のレガシー値が残っていないこと）

```bash
fly ssh console --app live-souls -C 'php /var/www/html/artisan tinker --execute="echo json_encode(DB::select(\"SELECT user_id, COUNT(*) as cnt FROM persons WHERE (phone IS NOT NULL AND phone != '''' AND phone NOT LIKE ''e2e:%'') OR (address IS NOT NULL AND address != '''' AND address NOT LIKE ''e2e:%'') GROUP BY user_id\"));"'
```

→ **実行者自身のuser_idの行が出力に存在しないこと**が合格。他ユーザーの行が残っているのは正常（各自のログイン時に移行される）。出力が `[]` なら全ユーザー移行済み。

### 4-5. 友人に開放

4-3と4-4が確認できたら、友人に通常利用してもらってよい。

## 5. ロールバック（万一の場合）

### 主経路: pg_restore（§1のバックアップから復元）

pg_restoreはスキーマごとバックアップ時点に戻す。

```bash
# 1. アプリを停止（リストア中のDB書込みを防ぐ）
fly scale count 0 --app live-souls --yes

# 2. ダンプをPostgres VMにアップロードしてリストア（<パスワード>を§1で取得した値に置換）
fly sftp shell --app live-souls-db
put backup_before_deployB.dump /tmp/backup_before_deployB.dump
exit
fly ssh console -C "sh -c 'pg_restore --clean --if-exists -d \"postgres://live_souls:<パスワード>@live-souls-db.flycast:5432/live_souls?sslmode=disable\" /tmp/backup_before_deployB.dump'" --app live-souls-db

# 3. 旧イメージでデプロイ（アプリ起動はここで行われる）
fly releases
fly deploy --image <1つ前のimage>

# 4. 復元確認（旧コードはencryptedキャストのため、DB::tableでeyJ始まりの暗号文が表示されるのが正常）
fly ssh console -C "php /var/www/html/artisan tinker --execute=\"\\\$r = DB::table('persons')->first(); echo 'name=' . substr(\\\$r->name, 0, 10) . ' phone=' . substr(\\\$r->phone, 0, 10);\"" --app live-souls
```

→ `name=eyJpdiI6Il phone=eyJpdiI6Il` のように `eyJ` で始まる暗号文が表示されれば正常（旧コードの `encrypted` キャストによるAPP_KEY暗号化値がそのまま出力される）。平文が出た場合はリストアに問題がある。

```bash
# 5. 後片付け
fly ssh console -C "rm /tmp/backup_before_deployB.dump" --app live-souls-db
```

実行順の理由:
- `scale count 0` でアプリを停止 → pg_restoreでDBを復元 → 旧イメージdeployでアプリ起動
- この順序により、restore中にアプリがDBに書き込む穴がない
- `fly deploy --image` は `scale count 0` 後でもマシンを再作成して起動する
