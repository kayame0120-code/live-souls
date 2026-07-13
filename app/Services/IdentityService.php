<?php

namespace App\Services;

use App\Models\FcMembership;
use App\Models\Person;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IdentityService
{
    /**
     * 会員番号の下3桁ヒントを解決する（一覧の「下3桁だけ常時表示」用）。
     * クライアントが別送したヒント(最大3文字)をそのまま保存する。
     * サーバーは平文を受け取らない設計（フェイルクローズ・基準No.11）。
     */
    public static function resolveMemberNoHint(?string $memberNo, ?string $clientHint): ?string
    {
        if ($memberNo === null || $memberNo === '') {
            return null;
        }

        return $clientHint !== null && $clientHint !== '' ? mb_substr($clientHint, -3) : null;
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
                'member_no' => $membershipData['member_no'] ?? null,
                'member_no_hint' => self::resolveMemberNoHint(
                    $membershipData['member_no'] ?? null, $membershipData['member_no_hint'] ?? null
                ),
                'login_id' => $membershipData['login_id'] ?? null,
                'email' => $membershipData['email'] ?? null,
                'password' => $membershipData['password'] ?? null,
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
                'member_no' => $membershipData['member_no'] ?? null,
                'login_id' => $membershipData['login_id'] ?? null,
                'email' => $membershipData['email'] ?? null,
                'joined_on' => $membershipData['joined_on'] ?? null,
                'oshi_color' => $membershipData['oshi_color'] ?? null,
                'group_member_id' => $membershipData['group_member_id'] ?? null,
            ];

            $hint = self::resolveMemberNoHint(
                $membershipData['member_no'] ?? null, $membershipData['member_no_hint'] ?? null
            );
            if ($hint !== null || empty($membershipData['member_no'])) {
                $update['member_no_hint'] = $hint;
            }

            if (! empty($membershipData['password'])) {
                $update['password'] = $membershipData['password'];
            }

            $membership->update($update);

            return $membership->fresh();
        });
    }
}
