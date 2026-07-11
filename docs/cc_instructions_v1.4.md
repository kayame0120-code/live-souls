# 現場手帖 — CC/CoWork 作業指示 v1.4

対象リポジトリ: 現場手帖（Laravel 12 / PHP 8.2 / Fortify / PostgreSQL・SQLite / Fly.io nrt）
正本: `docs/spec.md` v1.4 ／ デザイン: `docs/mockup.html`（v1.4改訂済）
本書は **v1.3→v1.4 の差分作業指示**。v1.3で着手済み・完了済みの内容は本書冒頭「§0 v1.3からの引き継ぎ」で整理する。本書と spec.md が矛盾したら spec.md を優先し QUESTIONS.md に記録すること。

---

## 実装者への指示（削除禁止）

- 本書と spec.md に明記されていない仕様を、実装者が独自に決定することを**禁止**する。未決・不明点は実装せず QUESTIONS.md に起票して返すこと（v1.3までの運用と同じ）。
- ステータス凡例: **[確定]**=変更禁止 / **[既定]**=デフォルト・変更は要確認 / **[未決]**=実装禁止。
- CLAUDE.md（恒久・共通ルール）は**編集・再生成しない**。
- 破壊的変更を含む。**マイグレーション前に必ずDBダンプ**を取ること。
- 検証は自己申告禁止。実際のコマンド出力を REPORT.md に貼ること。

---

## 0. v1.3からの引き継ぎ（QUESTIONS.md 現況の反映）

前回提出された QUESTIONS.md を踏まえ、状況を次のとおり整理する。

| 項目 | v1.3時点の状況 | v1.4での扱い |
|---|---|---|
| QV13-4（spec.mdがv1.2のまま） | 指摘の通り未更新だった | **解消**。本書が参照する spec.md は v1.4。旧v1.2/v1.3の内容もマージ済み |
| QV13-1（開演時間の表示源の二重化：events.start_time ↔ attendances.start_time/open_time） | 未解決。events.start_time追加のみ確定・表示張り替えは保留 | **本書でも未解決のまま引き継ぐ**。片倉からA/B/C（開演=公演一本化／参戦側残す／開演=公演・開場=参戦の役割分担）の指示待ち。**解決するまで公演名表示の張り替え（§5手順6）に着手しないこと**。両者は独立した作業単位にできるので、tours関連の実装はQV13-1を待たず進めてよい |
| QV13-2（EventImportDemo.jsx 原本欠落） | 未着手。原本が見つからずBをブロック | **原本はチャットで片倉から提供済み**。`docs/` または `tests/fixtures/` へ配置されているか確認すること。無ければ片倉に確認を求める。**tours新設により一括インポートの確認テーブル仕様が変わる**（§4参照）ため、原本投入後は旧cc_instructions_v1.3.mdの§3ではなく本書§4に従うこと |
| QV13-3（arena-view資産欠落） | 未着手。venue_engine_template.html等が無い | **未解決のまま引き継ぐ**。tours新設とは無関係（360°は会場詳細内で完結）。資産が揃うまで器（route/controller/404/導線出し分け）のみ実装し、中身は保留でよい |

**v1.3で既に完了しているもの**（再実施不要）：`events.start_time`（time, nullable）カラム追加のマイグレーション。

---

## 1. このバージョンでやること

| # | 内容 | 種別 |
|---|---|---|
| D | `tours` テーブルを新設し、`events.tour_id` を必須化する | 破壊 |
| E | `events.event_name` を `event_label`（nullable・役割変更）へ改名する | 破壊 |
| F | 公演一覧・当落画面をツアー階層（ツアー一覧→詳細）に変更する | 追加 |
| G | 一括インポートにツアー名解決を追加する（B着手時。QV13-2解消後） | 追加 |

やらないこと：QV13-1（開演表示源）の解決、QV13-3（360°ジオメトリ本体）の実装。いずれも片倉の判断・資産投入待ち。

---

## 2. マイグレーション [確定]

**厳守：ステップは順序通り。各ステップ後にテストが緑であることを確認してから次へ。**

### 2-1. `tours` テーブル新設

```
Schema::create('tours', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->timestamps();
});
```

- user_id なし（共有マスタ・venues/eventsと同型）。

### 2-2. 既存 events から tours を逆生成 [確定]

**この手順は推測で書かず、以下のルールに厳密に従うこと。**

```php
// 概念コード（実際はマイグレーション内 or 専用コマンドで実装）
$groups = Event::query()->select('event_name')->distinct()->get();
foreach ($groups as $g) {
    $tour = Tour::create(['name' => $g->event_name]);
    Event::where('event_name', $g->event_name)->update(['tour_id' => $tour->id]);
}
```

- **同じ `event_name` 文字列を持つ event 群は同一 tour に集約する**。異なる文字列は別 tour（自動での意味的統合・分割は絶対にしない＝安全側）。
- 実行後、**全 events の tour_id が非NULLであることを検証**してから次へ進む。1件でも漏れがあれば停止して QUESTIONS.md へ。

### 2-3. `events.tour_id` を NOT NULL 化 [確定]

2-2の検証通過後のみ実行。

```
Schema::table('events', fn(Blueprint $t) =>
    $t->foreignId('tour_id')->nullable(false)->change()
);
```

### 2-4. `event_name` → `event_label` [確定・単純リネーム禁止]

**重要：`renameColumn` を使わないこと。** 値の意味が変わる（旧＝ツアー名込みの全文、新＝日程だけの差分ラベル）ため、新カラムを追加し空で初期化し、旧カラムをDROPする。

```
Schema::table('events', fn(Blueprint $t) =>
    $t->string('event_label')->nullable()->after('tour_id')
);
// event_label は全件 NULL のまま（旧event_nameの全文は既にtours.name側に転写済み）
// 検証後：
Schema::table('events', fn(Blueprint $t) => $t->dropColumn('event_name'));
```

- Eventモデルの `$fillable` を更新（event_name削除・tour_id/event_label追加）。

---

## 3. 表示の張り替え [確定・ただしQV13-1解決後に着手]

公演名を表示している箇所は全て `tours.name`（＋`event_label` があれば結合）に変更する。対象箇所をチェックリスト化してテストで担保すること。

| 箇所 | 旧表示 | 新表示 |
|---|---|---|
| ホーム「次の現場」 | `event.event_name` | `event.tour.name`（+event_label） |
| 参戦記録カード | `event.event_name` | 同上 |
| 参戦詳細タイトル | `event.event_name` | 同上 |
| 名義詳細の申込一覧 | `event.event_name` | 同上 |
| 「参戦した？」確認カード | `event.event_name` | 同上 |

**⚠️ この作業は QV13-1（開演時間の表示源）の解決と隣接するが別問題。** QV13-1が公演名表示自体をブロックするわけではないので、tours関連の表示張り替えは進めてよい。ただし同じ表示コンポーネントを触ることになるため、**両方の変更を同時に1箇所へ書くのではなく、tours分（本章）とQV13-1分（開演時間）は別コミットに分離**し、QV13-1の指示が来た時にコンフリクトしにくくすること。

---

## 4. 画面とルーティング [確定]

### 4-1. 新規ルート

```
GET  /tours/create              → TourController@create
POST /tours                     → TourController@store（作成後 /tours/{id} へリダイレクト）
GET  /tours/{tour}              → TourController@show（日程一覧）
GET  /tours/{tour}/events/create → EventController@create（旧 /events/create を置換）
POST /tours/{tour}/events        → EventController@store
GET  /lots/tours/{tour}          → LotController@showByTour（当落詳細）
```

### 4-2. 既存ルートの変更

- `/events`（公演一覧）：**ツアーカード一覧**に変更。`Tour::withCount('events')` 等でn公演を集計。
- `/lots`（当落一覧）：**ツアーカード一覧**に変更。`attendances.event_id → events.tour_id` を辿り、配下に `pivot.result = pending` を含む申込があれば「当落待ちあり」、無ければ「発表済」をサマリ表示。
- `/events/create` は廃止（4-1の `/tours/{tour}/events/create` に統合）。

### 4-3. ボタン配置 [確定]

- ツアー一覧画面：「＋ツアーを追加」（`/tours/create`へ）。
- ツアー詳細画面：「＋日程を追加」（`/tours/{id}/events/create`へ）。
- mockup.html の `.ev-new`（ツアー一覧側）・`.m-add`（ツアー詳細側の「＋日程を追加」）が正。

---

## 5. 一括インポートのツアー名解決 [確定・QV13-2解消後に着手]

`EventImportDemo.jsx` の `parse()` 自体は**変更しない**（v1.3指示のまま）。変わるのは確認テーブル確定時の**解決先**のみ。

- `parse()` が返す `tour`（1回の貼り付けにつき1つ、全行共通）を、**venue名寄せと同じパターン**で解決する：
  1. 既存 `tours.name` を検索し完全一致があればそれに紐付け。
  2. 無ければ新規作成候補として提示し、確定操作で `tours` へ INSERT。
- 確認テーブルのツアー名入力欄（jsx側の `tour` state・全行一括適用の1箇所）を、**検索付きセレクト**にUI変更する（venue解決の入力と同じ体験）。
- `event_label` は `parse()` が抽出しないため、確認テーブルでは空のまま。ユーザーが必要なら手動入力する。

**この作業は QV13-2（EventImportDemo.jsx原本）の解消が前提**。原本が repo に無いままツアー解決ロジックだけ先行実装すると、突合検証（v1.3のT1）ができないため着手しないこと。

---

## 6. 当落画面のツアー単位グルーピング [確定]

- 新テーブル不要。`attendances.event_id → events.tour_id` を辿る**表示ロジックのみ**で実現する。
- `/lots/tours/{tour}` では、そのツアー配下の events を日付順に取得し、それぞれに紐づく attendances + attendance_identity を「当落待ち（pending含む）」「結果（pending 0件）」で区分表示する（mockup.html の `#scr-lot-detail` が正）。
- pivot.result の実体・当選昇格ロジックは無改修（v1.2〜v1.3のまま）。

---

## 7. テスト（全件担保・REPORT.mdに出力）[確定]

| # | テスト | 期待 |
|---|---|---|
| U1 | tour逆生成の機械検証 | 全 events に tour_id が非NULLで埋まる。同一event_nameは同一tourに集約されている |
| U2 | tour_id NOT NULL制約 | 制約適用後、tour_idを指定しない event 作成が失敗する |
| U3 | event_label初期化 | 移行後、全 event_label が NULL。旧 event_name の値が失われていない（tours.name側に存在することを突合） |
| U4 | ツアー一覧・詳細の表示 | `/events` がツアーカード一覧、`/tours/{id}` が日程一覧を正しく表示する |
| U5 | 「+ツアーを追加」「+日程を追加」導線 | それぞれ正しい画面に遷移し、作成後の遷移先が正しい |
| U6 | 当落のツアー単位グルーピング | `/lots` のサマリ（当落待ちあり/発表済）と `/lots/tours/{id}` の内訳が pivot.result の実データと一致する |
| U7 | 一括インポートのツアー名解決（QV13-2解消後） | 既存tour一致・新規tour作成の両方が確認テーブルで正しく機能する |
| U8 | 既存回帰 | v1.3までの全テスト（更新期間境界・当選昇格・タイムラインフィルタ・削除規則・他人データ404／写真削除403・start_time付き昼夜別レコード）が緑のまま |

---

## 8. 完了の定義

- U1〜U6・U8 が緑（REPORT.md にコマンド出力添付）。U7 はQV13-2解消後に着手・完了。
- mockup.html（v1.4）の見た目・導線に準拠。
- QV13-1（開演時間の表示源）・QV13-3（360°ジオメトリ本体）は本書の対象外。解決済みにしない。QUESTIONS.mdに残したまま。
- 未確定事項・想定外の分岐は QUESTIONS.md に起票して返す。

---

作業指示 v1.4 ／ 正本: docs/spec.md v1.4 ／ 恒久ルール: CLAUDE.md（編集しない）／ デザイン: docs/mockup.html ／ 前バージョン: docs/cc_instructions_v1.3.md（§0参照）
