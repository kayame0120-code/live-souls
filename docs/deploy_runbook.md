# deploy_runbook.md — デプロイA: persons.name / birth_date 暗号化

> 人間が上から順に実行する。

## 0. ローカル検証完了（実施済み）

### V1: マイグレーション実行 — YES

fresh DB → 平文シード → rollback でencrypt未適用に → migrate で暗号化実行。

```
$ rm database/database.sqlite && touch database/database.sqlite
$ php artisan migrate --force
  （40マイグレーション全DONE）
  2026_07_13_050601_encrypt_persons_name_and_birth_date ......... 15.01ms DONE

$ php artisan db:seed --class=StartoSeeder --force
$ php artisan tinker  # 平文3行投入（山田花子/佐藤次郎/NULL）
$ php artisan migrate:rollback --step=1 --force
  2026_07_13_050601_encrypt_persons_name_and_birth_date ......... 18.58ms DONE
```

平文存在確認:
```
$ php artisan tinker --execute="echo json_encode(DB::select(\"SELECT COUNT(*) ...\"));"
[{"cnt":2}]
```

暗号化マイグレーション実行:
```
$ php artisan migrate --force
  2026_07_13_050601_encrypt_persons_name_and_birth_date ......... 26.08ms DONE
```

暗号化後:
```
[{"cnt":0}]
```

冪等性確認:
```
$ php artisan migrate --force
  Nothing to migrate.
$ php artisan tinker --execute="echo App\Models\Person::withoutGlobalScopes()->first()->name;"
山田花子
```

### V2: /up HTTP 200 — YES

```
200
```

### V3: テスト全通過 — YES

```
Tests:    123 passed (308 assertions)
Duration: 1.49s
```

## 1. バックアップ取得＋サイズ確認

pg_dump はアプリイメージに存在しない（`executable file not found in $PATH`）。
Fly Postgres VM（`live-souls-db`）から実行する。

```bash
# アプリVMからDATABASE_URLを取得
fly ssh console -C "sh -c 'echo \$DATABASE_URL'" --app live-souls
# → postgres://live_souls:****@live-souls-db.flycast:5432/live_souls?sslmode=disable

# Postgres VMでpg_dump実行
fly ssh console -C "sh -c 'pg_dump \"postgres://live_souls:****@live-souls-db.flycast:5432/live_souls?sslmode=disable\" --format=custom --file=/tmp/backup_before_deployA.dump'" --app live-souls-db

# サイズ確認（サーバー側）
fly ssh console -C "ls -lh /tmp/backup_before_deployA.dump" --app live-souls-db

# ローカルにダウンロード
fly sftp get /tmp/backup_before_deployA.dump ./backup_before_deployA.dump --app live-souls-db

# サイズ確認（ローカル側）
ls -lh ./backup_before_deployA.dump
```

予行実績（2026-07-13実行）:
```
$ fly ssh console -C "sh -c 'pg_dump \"postgres://...\" --format=custom --file=/tmp/backup_test.dump'" --app live-souls-db
Connecting to fdaa:78:f2d5:a7b:c0:6d49:e85f:2...
（出力なし＝成功）

$ fly ssh console -C "ls -lh /tmp/backup_test.dump" --app live-souls-db
-rw-r--r-- 1 root root 99K Jul 13 06:28 /tmp/backup_test.dump

$ fly sftp get /tmp/backup_test.dump ./backup_test.dump --app live-souls-db
100379 bytes written to ./backup_test.dump

$ ls -lh ./backup_test.dump
-rw-r--r-- 1 ayame ayame 99K Jul 13 15:28 ./backup_test.dump
```

リストア実演（Postgres VM上の使い捨てDB）:
```
$ fly ssh console -C "sh -c \"psql '...live_souls...' -c 'CREATE DATABASE restore_test;'\"" --app live-souls-db
CREATE DATABASE

$ fly ssh console -C "sh -c 'pg_restore --clean --if-exists -d \"...restore_test...\" /tmp/backup_test.dump'" --app live-souls-db
RESTORE_EXIT:0

$ fly ssh console -C "sh -c \"psql '...restore_test...' -c 'SELECT COUNT(*) FROM persons;'\"" --app live-souls-db
 count
-------
    16
(1 row)

# 後片付け
$ fly ssh console ... -c 'DROP DATABASE restore_test;'
DROP DATABASE
$ fly ssh console -C "rm /tmp/backup_test.dump" --app live-souls-db
$ rm ./backup_test.dump
```

## 2. fly.toml の release_command（コミット済み）

```toml
[deploy]
  release_command = 'php /var/www/html/artisan migrate --force'
```

`fly deploy` 時に release_command でマイグレーションが実行され、完了後に新コードに切り替わる。
コードとマイグレーションの時間差による DecryptException を回避するための措置。

## 3. デプロイ（深夜帯に実行）

```bash
fly deploy
```

release_command が `php artisan migrate --force` を実行 → 成功後にトラフィック切替。
ログで DONE を確認:
```bash
fly logs | grep -i migrate
```

## 4. 動作確認

### /up ヘルスチェック

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://live-souls.fly.dev/up
```

→ `200` であること。

### ブラウザ3項目

| # | 確認内容 | 手順 |
|---|---|---|
| 1 | 名義一覧で氏名が正常表示 | `/identities` を開き、名義カードに氏名が出ていること |
| 2 | 名義詳細で誕生日・年齢が正常表示 | 任意の名義をタップし、誕生日（YYYY.MM.DD）と年齢（○歳）が表示されること |
| 3 | 名義編集で誕生日の編集が動作 | 名義編集画面で誕生日フィールドに既存値が入っていること。変更→保存→再表示で反映されること |

## 5. 検証SQL（平文行 0件の確認）

```bash
fly ssh console -C "php /var/www/html/artisan tinker --execute=\"echo json_encode(DB::select(\\\"SELECT COUNT(*) as cnt FROM persons WHERE (name IS NOT NULL AND name !~ '^eyJ') OR (birth_date IS NOT NULL AND birth_date !~ '^eyJ')\\\")); \"" --app live-souls
```

→ `[{"cnt":0}]` であること。

## 6. ロールバック（万一の場合）

### 主経路: pg_restore（§1のバックアップから復元）

pg_restore はスキーマごとバックアップ時点に戻すため、マイグレーションの down() に依存しない。
**旧イメージ稼働下で実行すること**（新コードのencryptedキャストが平文を読んでDecryptExceptionになるため）。

```bash
# 1. 旧イメージに戻す（コードを先に旧版に切り替え）
fly releases
fly deploy --image <1つ前のimage>

# 2. §1のダンプをPostgres VMにアップロードしてリストア
fly sftp shell --app live-souls-db
put backup_before_deployA.dump /tmp/backup_before_deployA.dump
exit
fly ssh console -C "sh -c 'pg_restore --clean --if-exists -d \"postgres://live_souls:****@live-souls-db.flycast:5432/live_souls?sslmode=disable\" /tmp/backup_before_deployA.dump'" --app live-souls-db

# 3. 復元確認（平文の氏名が表示されること）
fly ssh console -C "php /var/www/html/artisan tinker --execute=\"echo DB::table('persons')->first()->name;\"" --app live-souls

# 4. 後片付け
fly ssh console -C "rm /tmp/backup_before_deployA.dump" --app live-souls-db
```

実行順の理由:
- 先にコードを旧版に戻す → pg_restoreで平文データに復元 → 旧コード（encryptedキャストなし）が平文を正常に読める
- 逆順（先にrestore）だと、新コードが平文を読んでDecryptExceptionになる
