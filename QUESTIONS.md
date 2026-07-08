# QUESTIONS.md — 現場手帖

## v1.1 改修時（2026-07-08）

### Q5: HEIC は受付対象から除外（[既定] の変更・要確認）

- **spec根拠**: §4 attendance_photos 制約「jpeg・png・webp・heic受付 / 保存時にEXIF除去」[既定]
- **問題**: 実行環境（ローカル PHP 8.2 / 本番想定同等）に imagick が無く GD のみ。
  GD は HEIC をデコードできず、**再エンコードによる EXIF 除去を保証できない**
- **仮置き（安全側）**: 受付 mimes を jpeg/png/webp に限定し、HEIC は拒否
  （EXIF除去できないまま保存するより安全側と判断）
- **解除条件**: imagick + libheif が導入されたら mimes に heic を追加し PhotoService に変換を実装
- **実装箇所**: `AttendanceController::validatedData()` / `PhotoService`

### 記録: 破壊的DROP前の現物値（spec §10-1 の記録義務）

- 対象データ: fc_memberships 全1件（id=1）
- `club_name` = **null**（非null値なし）
- `renewal_cycle` = **null**（非null値なし・spec記載どおり）
- `joined_month` = `"2022-10"` → `joined_on` = `2022-10-01` に変換
  （`genba:verify-joined-on` で件数・値一致を機械検証済み。生出力は REPORT.md 参照）

### v1.0 からの繰越の解決

| No | 内容 | v1.1での扱い |
|---|---|---|
| Q2 | E1: グループ削除時の配下名義の扱い | **解決**。spec v1.1 §7/§8 で A案（削除拒否）[確定]。実装・テスト済み |
| Q4 | 当落結果の入力動線が spec に無い | **解決**。spec v1.1 §5-7-2「/lots で result を更新できる」[確定]。当落画面・参戦詳細の両方に実装 |

### Q3（継続）: 参戦記録の削除機能は spec 画面仕様に存在しない

- v1.1 spec §7 の削除拒否メッセージ「先に**名義を削除**または移動してください」により
  **名義削除は spec が前提とする機能になった**と解釈（名義削除の残置は正当化）
- **参戦記録の削除**は引き続き spec に記載なし。誤記帳の修正に必要なため残置継続
- **人間への依頼**: 参戦削除の要否を spec に明記してください

## 過去の記録（v1.0）

- Q1: laravel-starter 未セットアップ → 解決済み（2026-07-08 starter化実施）
