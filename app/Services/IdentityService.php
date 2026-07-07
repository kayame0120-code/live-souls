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
                'label' => $personData['label'] ?? null,
            ]);

            return FcMembership::create([
                'user_id' => Auth::id(),
                'person_id' => $person->id,
                'group_id' => $membershipData['group_id'],
                'artist_name' => $membershipData['artist_name'],
                'club_name' => $membershipData['club_name'] ?? null,
                'member_no' => $membershipData['member_no'] ?? null,
                'login_id' => $membershipData['login_id'] ?? null,
                'password' => $membershipData['password'] ?? null,
                'joined_month' => $membershipData['joined_month'] ?? null,
                'renewal_cycle' => $membershipData['renewal_cycle'] ?? null,
                'oshi_color' => $membershipData['oshi_color'] ?? null,
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
                'label' => $personData['label'] ?? null,
            ]);

            $membership->update([
                'group_id' => $membershipData['group_id'],
                'artist_name' => $membershipData['artist_name'],
                'club_name' => $membershipData['club_name'] ?? null,
                'member_no' => $membershipData['member_no'] ?? null,
                'login_id' => $membershipData['login_id'] ?? null,
                'password' => $membershipData['password'] ?? null,
                'joined_month' => $membershipData['joined_month'] ?? null,
                'renewal_cycle' => $membershipData['renewal_cycle'] ?? null,
                'oshi_color' => $membershipData['oshi_color'] ?? null,
            ]);

            return $membership->fresh();
        });
    }
}
