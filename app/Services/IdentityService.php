<?php

namespace App\Services;

use App\Models\FcMembership;
use App\Models\Person;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IdentityService
{
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

            // FCパスワードは入力があった場合のみ更新（空送信で既存値を消さない）
            if (! empty($membershipData['password'])) {
                $update['password'] = $membershipData['password'];
            }

            $membership->update($update);

            return $membership->fresh();
        });
    }
}
