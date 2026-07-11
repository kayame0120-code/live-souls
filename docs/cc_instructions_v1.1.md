# CC作業指示: 現場手帖 v1.0 → v1.1 改修

> 前提: `CLAUDE.md`（恒久ルール）に従うこと。本指示は docs/spec.md **v1.1** の適用作業。
> 添付の spec.md v1.1 で `docs/spec.md` を**丸ごと置き換える**（部分マージ禁止）。
> spec.md に明記されていない仕様を独自に決定することを禁止する。未決・矛盾は QUESTIONS.md へ。

## 0. 最初にやること

1. `docs/spec.md` を v1.1 で置き換え、変更履歴とバージョン表記が v1.1 であることを確認。
2. `docs/mockup.html` の改訂対象を確認（本指示 §4）。
3. **ローカルSQLiteのバックアップ**: `cp database/database.sqlite database/database.sqlite.v1.0.bak`（マイグレーション前に必須）。

## 1. 破壊的変更（DB）— 順序厳守

spec.md §10 の通り。要点:

### 1-1. fc_memberships 変換マイグレーション
1. `joined_on` (date, nullable) 追加。
2. 既存レコードを**PHP側で**変換コピー: `joined_month` が `/^\d{4}-\d{2}$/` に一致 → `{値}-01` を joined_on へ。
   - 一致しない値が1件でもあれば**変換を中断し、該当値を QUESTIONS.md に記録して停止**。
   - 現状確認済みデータ: id=1 `"2022-10"` / renewal_cycle=null（2026-07-08 片倉確認）。
3. 変換の機械検証: 変換前後で「joined_month 非nullの件数 == joined_on 非nullの件数」かつ各行 `joined_on == joined_month . "-01"` をartisanコマンド or テストで突合。目視照合をユーザーにさせない。
4. 検証通過後、`joined_month` / `renewal_cycle` / `club_name` をDROP。
   - DROP前に club_name・renewal_cycle の非null値が存在すれば QUESTIONS.md に現物を記録してからDROP（renewal_cycleは全null確認済み）。
5. SQLite/PG両対応: 生SQL・DB方言禁止（CLAUDE.md）。Laravelスキーマビルダのみ使用。

### 1-2. attendance_photos 作成
spec.md §4 の定義通り。user_id は投稿者。**Eloquentのグローバルuser_idスコープを適用しない**（閲覧はメンバー間共有・規約0-6）。書込・削除の認可は投稿者本人のみ（Policy必須）。

## 2. 機能改修・追加（依存順）

| 順 | 作業 | spec参照 |
|---|---|---|
| 1 | 廃止カラム参照の除去（club_name / joined_month / renewal_cycle をモデル・フォーム・Blade・テストから排除） | §4 |
| 2 | グループ削除ガード: 配下 fc_memberships が1件以上なら削除拒否＋メッセージ「先に名義を削除または移動してください」 | §7 |
| 3 | 更新期間アクセサ（有効期限・受付期間・受付中判定）＋名義詳細の表示とバッジ | §5-6 |
| 4 | 申込登録 `/lots/create` ＋ 当選時の planned 自動昇格 ＋ タイムラインの applied 除外 | §5-7 |
| 5 | 参戦登録フォームの座席3フィールド化＋seat_raw自動合成（手動編集優先ロジック含む） | §5-8 |
| 6 | 公演名サジェスト（自分＋メンバーの過去 event_name） | §5-1 |
| 7 | 写真添付（アップロード・EXIF除去・枚数/サイズ制限）＋ 参戦詳細表示 | §4, §6 |
| 8 | 会場詳細の見え方マッピング（タイル表示・投稿者名・削除Policy） | §5-9 |
| 9 | 貼り付け一括インポート `/lots/import`（パーサ＋確認テーブル＋一括登録） | §5-10 |
| 10 | 招待登録画面の同意文言 | §5-4 |
| 11 | Places API 会場オートフィル（失敗時フォールバック必須） | §5-11 |

## 3. テスト必須（spec.md §7「テスト化必須」の全件）

- グループ削除: 配下ありで拒否 / 配下0件で成功
- 更新期間: 1月入会（同年内）/ 2月入会（年跨ぎ）/ 受付初日2日・期限日当日が「受付中」/ joined_on null で非表示
- タイムライン: applied 非表示、planned/attended/skipped 表示（skippedグレー）
- 当選昇格: won 1件で planned 化 / 全lost で applied のまま
- 写真: 他ユーザーは閲覧可・削除403 / 6枚目拒否 / 10MB超拒否 / EXIF除去
- joined_month変換: 突合検証（§1-1-3）
- 既存の必須テスト（403スコープ・招待多重使用・うるう年）は退行させない

## 4. mockup.html 改訂対象

- 名義カードの担当色: バー → **丸スウォッチ**
- 当落画面: 申込登録・一括インポートへの導線を追加
- 会場詳細: 見え方マッピング（写真タイル）セクションを追加
- 参戦登録: 座席3フィールド＋seat_raw の並び

## 5. 環境・インフラ（実装はするが、外部設定は片倉タスク）

| 項目 | CC側 | 片倉側 |
|---|---|---|
| 写真ストレージ | filesystems設定（local / s3切替）。本番はTigris前提のS3ドライバ | Fly Tigrisバケット作成・シークレット設定 |
| Places API | 環境変数からキー読込。未設定でも動作（フォールバック） | Google Cloudプロジェクト・APIキー・課金設定 |

## 6. やらないこと（提案も禁止）

- 座席図ビジュアル / 更新日数カウントダウン・通知 / ローカル完結UI / スクショOCR（v1.2候補・今回対象外）
- E1の再解釈（A案=削除拒否で確定済み。B/C案の提案不要）
- spec.md v1.1 に書かれていない仕様の独自補完
