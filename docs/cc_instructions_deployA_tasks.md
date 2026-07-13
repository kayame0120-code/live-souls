# cc_instructions_deployA_tasks.md — 実行タスク2件（runbook改稿は禁止）

## このタスクの成果物の定義（最重要・最初に読む）

- **成果物はコマンドの生ログである。文書ではない。**
- deploy_runbook.md を含む一切の md ファイルの編集を**禁止**する。
  runbook v4 は、本書のログ2件が承認された後に別タスクとして指示する。
- 「YES/完了/実施済み」の申告は、**該当ログ行の引用が添付されているものだけ**を有効とする。
  引用のない YES は未回答として扱う。
- コマンドが失敗した場合、**失敗ログをそのまま提出することが正しい完了**である。
  動く形への修正は失敗ログを見てから別途判断する。勝手に直して成功ログだけを出さない。

## 権限の明示

- タスク1は全てローカル環境。承認不要。
- タスク2は本番だが**読み取り専用**（pg_dump / ls / sftp get）。
  CLAUDE.md の承認制コマンド（fly deploy / git push / gh pr merge）に該当しないため、
  **承認不要。即時実行してよい。** 本番への書込み・デプロイは一切含まれない。

---

## タスク1（ローカル）: マイグレーションの実挙動検証

目的: 「平文が入ったDBに対して暗号化マイグレーションが正しく動く」を、
本番実行前にローカルで1回再現する。`Nothing to migrate.` は検証として無効。

以下を**この順で**実行し、各ステップの生ログ（コマンド行＋出力全文）を提出する。

```bash
# 1-1. DBを初期化し、平文の persons を複数行投入する
php artisan migrate:fresh --seed
#   → seeder に persons が含まれない場合は factory 等で3行以上投入し、
#     投入方法もログに含める

# 1-2. 対象マイグレーション適用「前」の平文状態を証拠化
php artisan tinker --execute="echo json_encode(DB::select(\"SELECT COUNT(*) as cnt FROM persons WHERE (name IS NOT NULL AND name !~ '^eyJ') OR (birth_date IS NOT NULL AND birth_date !~ '^eyJ')\"));"
#   → cnt が 1 以上であること（0なら平文行が無い＝検証前提が壊れているので中断して報告）
#   ※ migrate:fresh は全マイグレーションを適用するため、対象マイグレーションだけ
#     未適用の状態を作る必要がある。migrations テーブルから対象1件を削除し
#     down 相当の状態を再現する等、採った方法をログで示すこと

# 1-3. 対象マイグレーションを実行
php artisan migrate --force
#   → 対象マイグレーション名が Running/Done として出力されていること
#     （Nothing to migrate. が出たら検証失敗として報告）

# 1-4. 適用「後」の平文0件を証拠化
（1-2 と同じコマンド） → [{"cnt":0}]

# 1-5. 冪等性: もう一度実行してエラーなし・二重暗号化なし
php artisan migrate --force
php artisan tinker --execute="echo Person::withoutGlobalScopes()->first()->name;"
#   → 復号された平文の氏名が正しく表示されること（二重暗号化なら JSON 文字列が出る）

# 1-6. テストスイート
php artisan test
#   → 結果全文（passed 件数の行を含む）
```

### タスク1の報告様式

| # | 判定 | 証拠（ログ該当行の引用） |
|---|---|---|
| T1-1 | | |
| T1-2 | | cnt=◯ の出力行 |
| T1-3 | | Running/Done の出力行 |
| T1-4 | | cnt=0 の出力行 |
| T1-5 | | 復号氏名の出力行 |
| T1-6 | | passed 件数の行 |

---

## タスク2（本番・読み取り専用）: バックアップコマンドの実挙動検証

目的: runbook §1 のコマンド列が本番で実際に動くかを、デプロイ当日ではなく今確認する。
懸念は2点: (a) `fly ssh console -C` はシェルを介さないため `$DATABASE_URL` が
展開されない可能性、(b) アプリイメージに pg_dump が存在しない可能性。
**どちらに転んでもログが成果物。**

```bash
# 2-1. そのまま実行してみる
fly ssh console -C "pg_dump \$DATABASE_URL --format=custom --file=/tmp/backup_test.dump"

# 2-2. 失敗した場合のみ、失敗ログ提出後に指示を待たず以下の代替を1つずつ試してよい
#     （試した順と各ログを全て提出する）
fly ssh console -C "sh -c 'pg_dump \$DATABASE_URL --format=custom --file=/tmp/backup_test.dump'"
fly ssh console          # 対話シェルに入り pg_dump を直接実行（実行したコマンドを記録）

# 2-3. 成功した形で、サイズ確認と取得
fly ssh console -C "ls -lh /tmp/backup_test.dump"
fly sftp get /tmp/backup_test.dump ./backup_test.dump
ls -lh ./backup_test.dump

# 2-4. リストア実演（ローカルの使い捨てDBに対して。★本番には絶対に restore しない）
createdb restore_test   # またはローカル環境の同等手段
pg_restore --clean --if-exists -d restore_test ./backup_test.dump
psql restore_test -c "SELECT COUNT(*) FROM persons;"
#   → 本番と同じ行数が返ること

# 2-5. 後片付け
fly ssh console -C "rm /tmp/backup_test.dump"
dropdb restore_test
#   ローカル取得分 backup_test.dump は個人情報を含むため、検証完了の報告後に削除し、
#   削除したことも報告に含める
```

### タスク2の報告様式

| # | 判定 | 証拠（ログ該当行の引用） |
|---|---|---|
| T2-1 | 成功/失敗 | 出力行（失敗ならエラー全文） |
| T2-2 | 該当時のみ | 試した代替と各ログ |
| T2-3 | | ダンプサイズの行（2箇所） |
| T2-4 | | 行数の出力行 |
| T2-5 | | 削除確認 |

---

## 禁止事項（再掲）

- md ファイルの編集（runbook 改稿は本タスクの範囲外）
- 失敗ログの省略・成功ログのみの提出
- 本番への書込み系コマンド（restore / migrate / deploy）の実行
- ログ引用のない「YES」「実施済み」
