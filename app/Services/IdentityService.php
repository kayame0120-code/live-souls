<?php

namespace App\Services;

use App\Models\FcMembership;
use App\Models\Person;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class IdentityService
{
    /**
     * E2E対象フィールド（member_no/login_id/password）の書き込み時保護。
     * - "e2e:"プレフィックス付き＝クライアント側で暗号化済み → そのまま保存
     * - それ以外（JS無効等のフォールバック）→ サーバー側標準暗号化(APP_KEY)で保存
     *   ※フォールバック時は基準No.11を満たさない。E2E統合済みブラウザでは発生しない
     */
    public static function protectE2eField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (FcMembership::isE2eValue($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * 会員番号の下3桁ヒントを解決する（一覧の「下3桁だけ常時表示」用）。
     * - 平文（フォールバック経路）: サーバー側で下3桁を算出
     * - E2E暗号文: クライアントが別送したヒントを使う（無ければnull=既存維持/なし）
     */
    public static function resolveMemberNoHint(?string $memberNo, ?string $clientHint): ?string
    {
        if ($memberNo === null || $memberNo === '') {
            return null;
        }
        if (FcMembership::isE2eValue($memberNo)) {
            return $clientHint !== null && $clientHint !== '' ? mb_substr($clientHint, -3) : null;
        }

        return mb_substr($memberNo, -3);
    }
    public function create(array $personData, array $membershipData): FcMembership
    {
        return DB::transaction(function () use ($personData, $membershipData) {
            $person = Person::create([
                'user_id' => Auth::id(),
                'name' => $personData['name'],
                'birth_date' => $personData['birth_date'] ?? null,
                'phone' => $personData['phone'] ?? null,
                'address' => $personData['address'] ?? null,
            ]);

            return FcMembership::create([
                'user_id' => Auth::id(),
                'person_id' => $person->id,
                'group_id' => $membershipData['group_id'],
                'artist_name' => $membershipData['artist_name'],
                'label' => $membershipData['label'] ?? null,
                'member_no' => self::protectE2eField($membershipData['member_no'] ?? null),
                'member_no_hint' => self::resolveMemberNoHint(
                    $membershipData['member_no'] ?? null, $membershipData['member_no_hint'] ?? null
                ),
                'login_id' => self::protectE2eField($membershipData['login_id'] ?? null),
                'email' => $membershipData['email'] ?? null,
                'password' => self::protectE2eField($membershipData['password'] ?? null),
                'joined_on' => $membershipData['joined_on'] ?? null,
                'oshi_color' => $membershipData['oshi_color'] ?? null,
                'group_member_id' => $membershipData['group_member_id'] ?? null,
            ]);
        });
    }

    public function update(FcMembership $membership, array $personData, array $membershipData): FcMembership
    {
        return DB::transaction(function () use ($membership, $personData, $membershipData) {
            $membership->person->update([
                'name' => $personData['name'],
                'birth_date' => $personData['birth_date'] ?? null,
                'phone' => $personData['phone'] ?? null,
                'address' => $personData['address'] ?? null,
            ]);

            $update = [
                'group_id' => $membershipData['group_id'],
                'artist_name' => $membershipData['artist_name'],
                'label' => $membershipData['label'] ?? null,
                'member_no' => self::protectE2eField($membershipData['member_no'] ?? null),
                'login_id' => self::protectE2eField($membershipData['login_id'] ?? null),
                'email' => $membershipData['email'] ?? null,
                'joined_on' => $membershipData['joined_on'] ?? null,
                'oshi_color' => $membershipData['oshi_color'] ?? null,
                'group_member_id' => $membershipData['group_member_id'] ?? null,
            ];

            // 下3桁ヒント: E2E値でヒント未送信の場合は既存値を維持（キーごと省略）
            $hint = self::resolveMemberNoHint(
                $membershipData['member_no'] ?? null, $membershipData['member_no_hint'] ?? null
            );
            if ($hint !== null || empty($membershipData['member_no'])) {
                $update['member_no_hint'] = $hint;
            }

            // FCパスワードは入力があった場合のみ更新（空送信で既存値を消さない）
            if (! empty($membershipData['password'])) {
                $update['password'] = self::protectE2eField($membershipData['password']);
            }

            $membership->update($update);

            return $membership->fresh();
        });
    }
}
