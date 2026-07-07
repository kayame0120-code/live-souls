# REPORT.md — 現場手帖 v1.0 実装レポート

## 検証ライン判定表（2026-07-08 レビュー後再検証）

| # | 判定条件 | コマンド | 結果 |
|---|---|---|---|
| V1 | マイグレーションがエラーなく通る | `php artisan migrate --force` | **YES** (exit 0) |
| V2 | `/up` が HTTP 200 | `curl ... http://127.0.0.1:8123/up` | **YES** (HTTP 200) |
| V3 | テストが全通過 | `php artisan test` | **YES** (29 passed, 60 assertions) |

## 実装内容

### Phase 0: laravel-starter化
- Laravel Fortify インストール・設定
- `Features::registration()` 無効化
- 招待コード付き登録 `/register/{code}` 実装（レースコンディション対策: `used_at` による先行ロック）
- ログインビュー作成
- 2FA・Passkeys は spec 範囲外のため除外

### Phase 1: マイグレーション
spec §4 の全テーブルを作成:
- `users` 拡張（`invited_by`）
- `invitations`, `identity_groups`, `persons`, `fc_memberships`
- `venues`, `venue_notes`
- `attendances`, `attendance_identity`

### Phase 2: モデル
- 全 9 モデル作成（User, Invitation, IdentityGroup, Person, FcMembership, Venue, VenueNote, Attendance, AttendanceIdentity）
- `encrypted` キャスト: Person.phone, Person.address, FcMembership.login_id, FcMembership.password
- `UserScope` グローバルスコープで `user_id` マルチテナント分離（Venue 除く）
- Person モデルは `$table = 'persons'` を明示（Laravel デフォルトの `people` を回避）

### Phase 3: Service・Controller・ルーティング
- Service 層: HomeService, AttendanceService, IdentityService, InvitationService
- Controller: Home, Attendance, Identity, IdentityGroup, Lot, Venue, Invitation, InvitedRegister
- spec §6 バリデーション全実装

### Phase 4: Blade ビュー
- mockup.html のデザイントークン（FC事務局×ヲタ活手帳）を CSS に統合
- 全画面実装: ログイン, 招待登録, ホーム, 参戦記録(一覧/登録/詳細/編集), 名義(一覧/詳細/登録/編集), グループ管理, 当落, 会場詳細, 招待管理
- 名義画面で FAB 非表示（spec §9 心理×デザイン整合）
- パスワード伏字 + コピーボタン（`navigator.clipboard` + textarea フォールバック）
- プライバシー文言を修正版に反映（「暗号化してサーバーに保存」）
- 空状態の表示を全画面で実装（spec §3）
- 会場サジェスト（部分一致・デバウンス付き）

### Phase 5: テスト
| テスト | spec 根拠 | 結果 |
|---|---|---|
| MultiTenantTest (5件) | §7: 他ユーザーへの直アクセス→403/404 | PASS |
| PersonAgeTest (6件) | §7: うるう年2/29 + §5-2: 年齢自動計算 | PASS |
| InvitationTest (6件) | §7: 招待コード同時多重使用 + §5-4 | PASS |
| WinRateTest (4件) | §5-3: 当選率ゼロ除算 | PASS |

## 実装手段の主要決定

| 決定 | 理由 |
|---|---|
| `used_at` で招待ロックし `used_by` は後から更新 | FK 制約違反回避 + レースコンディション対策 |
| `strftime` 不使用、PHP 側で年フィルタ | CLAUDE.md 「DB固有関数禁止」 |
| Person テーブル名 `persons` を明示 | Laravel の `people` デフォルトとの不整合回避 |
| Tailwind CSS v4 + カスタム CSS 併用 | mockup のデザイントークンを忠実に再現 |
| 2FA/Passkeys 除外 | spec に記載なし。Fortify 標準ログインのみ |

## セルフレビュー結果（2026-07-08 実施）

### 仕様不適合の修正

| # | 問題 | spec根拠 | 修正 |
|---|---|---|---|
| R1 | バリデーションエラーメッセージが英語標準のまま | §6 [確定]（「公演名を入力してください」等） | validate() に日本語メッセージ配列を追加 |
| R2 | 名義詳細で会員番号が平文表示 | S4 [確定]「会員番号・login_idも同様（伏字＋コピー）」 | 名義詳細内は `••••••••` 表示に変更（コピーで取得）。名義一覧カードはモックの会員証様式に従い表示を維持 |
| R3 | 当落結果（result）の入力動線が存在せず S5/S6 が機能しない | S5/S6 [確定] | 参戦詳細の名義行に結果更新フォームを追加（`PATCH /attendance-identities/{id}/result`）→ QUESTIONS.md Q4 に記録 |
| R4 | 名義編集でFCパスワードを空送信すると null で上書き | §0-2（機密の保全） | 空欄なら既存値を維持するよう Service を修正 |

### 脆弱性の修正

| # | 問題 | 修正 |
|---|---|---|
| V-a | `exists` バリデーションで他ユーザーの identity_ids / group_id を送信可能（IDOR） | `Rule::exists()->where('user_id', Auth::id())` に変更（前回コミット） |
| V-b | 会場サジェストの `innerHTML` による Stored XSS | DOM API（`textContent`）に置換（前回コミット） |
| V-c | グループ並び替えで他ユーザーの sort_order を更新可能 | 更新クエリに `user_id` 条件を追加（前回コミット） |
| V-d | 名義編集フォームに復号済みFCパスワードが value 属性で出力 | プレフィルを廃止（R4 と合わせて修正） |
| V-e | 招待登録成功時にセッション ID 未再生成（セッション固定） | `session()->regenerate()` を追加 |
| V-f | `/register/{code}` にレート制限なし（コード総当たり） | `throttle:10,1` を適用 |

### 残存リスク（許容と判断・要認識）

- **コピーボタンの `data-copy` 属性に復号済み平文**: spec §5-5 は「平文をDOMのテキストとして描画しない」であり、属性値は画面に表示されないため仕様範囲内と判断。ページソースには含まれるため、より強固にするならクリック時に API で取得する方式へ変更可能
- **名義編集フォームの login_id プレフィル**: 編集 UX 上必要なため維持（type=text）。パスワードと異なり空欄=維持の仕組みは適用していない
- **UserScope は `Auth::check()` 時のみ適用**: コンソール/キュー処理ではスコープが効かない。現状該当処理は無いが、将来バッチを追加する場合は明示的な `where('user_id', ...)` が必要

### 動作確認コマンド

```bash
# V1: マイグレーション
rm -f database/database.sqlite && touch database/database.sqlite
php artisan migrate --force

# V2: ヘルスチェック
php artisan serve --port=8123 &
SERVER_PID=$!; sleep 3
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8123/up
kill $SERVER_PID

# V3: テスト（29件）
php artisan test

# 手動確認用の初期データ投入（ユーザー + 招待コード）
php artisan tinker --execute="
\$u = App\Models\User::create(['name' => '片倉', 'email' => 'test@example.com', 'password' => Hash::make('Password123!')]);
\$i = App\Models\Invitation::create(['code' => Str::random(32), 'issued_by' => \$u->id]);
echo 'login: test@example.com / Password123!' . PHP_EOL;
echo 'invite: /register/' . \$i->code . PHP_EOL;
"

# ブラウザ確認
php artisan serve
# → http://127.0.0.1:8000/login でログイン
# → 名義グループ作成 → 名義追加 → 参戦記録 → 参戦詳細で当落更新 → 当落タブ・名義詳細の当選率を確認
```

## QUESTIONS.md 残件

| No | 内容 | ステータス |
|---|---|---|
| Q1 | laravel-starter 未セットアップ | **解決済み** |
| Q2 | E1: グループ削除時の配下名義の扱い | **未決**（「削除不可」で仮置き中） |
| Q3 | 参戦・名義の削除機能が spec 画面仕様に無い | **要判断**（残置で仮置き） |
| Q4 | 当落結果の入力動線が spec 画面仕様に無い | **要判断**（参戦詳細に最小実装で仮置き） |
