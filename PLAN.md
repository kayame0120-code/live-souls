# PLAN.md — 現場手帖 実装計画

## Phase 0: laravel-starter化（Fortify + 招待制）
- [ ] `composer require laravel/fortify`
- [ ] FortifyServiceProvider 作成・登録
- [ ] Actions 作成（CreateNewUser, UpdateUserPassword 等）
- [ ] `Features::registration()` 無効化
- [ ] ログインビュー作成
- [ ] 招待コード付き登録ルート `/register/{code}` 実装

## Phase 1: マイグレーション（spec §4 全テーブル）
- [ ] users 拡張（invited_by）
- [ ] invitations
- [ ] identity_groups
- [ ] persons
- [ ] fc_memberships
- [ ] venues
- [ ] venue_notes
- [ ] attendances
- [ ] attendance_identity

## Phase 2: モデル・リレーション・スコープ
- [ ] 全モデル作成 + encrypted キャスト
- [ ] user_id グローバルスコープ（venues 除く）
- [ ] リレーション定義

## Phase 3: Service・Controller・ルーティング
- [ ] HomeController（ダッシュボード）
- [ ] AttendanceController（CRUD + 会場サジェスト）
- [ ] IdentityController（名義人 + FC会員）
- [ ] IdentityGroupController（グループ管理）
- [ ] LotController（当落一覧）
- [ ] VenueController（会場詳細 + 個人メモ upsert）
- [ ] InvitationController（招待管理）
- [ ] web.php ルート定義

## Phase 4: Blade ビュー
- [ ] レイアウト（mockup.html デザイントークン準拠）
- [ ] 認証（ログイン・招待付き登録）
- [ ] ホーム（次の現場・積み上げ・直近の記録）
- [ ] 参戦記録（一覧・登録・詳細・編集）
- [ ] 名義（一覧・詳細・登録・編集）
- [ ] グループ管理
- [ ] 当落
- [ ] 会場詳細
- [ ] 招待管理

## Phase 5: テスト（spec §7 エッジケース）
- [ ] マルチテナント 403（他ユーザーの attendance/venue_note への直アクセス拒否）
- [ ] うるう年 2/29 誕生日の年齢計算
- [ ] 招待コード同時多重使用（最初のみ成功）
- [ ] 当選率ゼロ除算（申込0件→「—」表示）

## Phase 6: 検証ライン V1-V3
- [ ] V1: `php artisan migrate --force` exit 0
- [ ] V2: `/up` HTTP 200
- [ ] V3: `php artisan test` exit 0
- [ ] REPORT.md / QUESTIONS.md / deploy_runbook.md 完成
