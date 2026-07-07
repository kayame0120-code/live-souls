# REPORT.md — 現場手帖 v1.0 実装レポート

## 検証ライン判定表

| # | 判定条件 | コマンド | 結果 |
|---|---|---|---|
| V1 | マイグレーションがエラーなく通る | `php artisan migrate --force` | **YES** (exit 0) |
| V2 | `/up` が HTTP 200 | `curl ... http://127.0.0.1:8123/up` | **YES** (HTTP 200) |
| V3 | テストが全通過 | `php artisan test` | **YES** (23 passed, 46 assertions) |

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

## QUESTIONS.md 残件

| No | 内容 | ステータス |
|---|---|---|
| Q1 | laravel-starter 未セットアップ | **解決済み** |
| Q2 | E1: グループ削除時の配下名義の扱い | **未決**（「削除不可」で仮置き中） |
