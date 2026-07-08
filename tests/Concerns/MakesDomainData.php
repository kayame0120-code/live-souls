<?php

namespace Tests\Concerns;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use App\Models\Venue;

/**
 * v1.2 のドメインデータ（events / attendances / 名義）を組み立てるテストヘルパー。
 * attendances は event_id 経由になったため、公演の生成をまとめる。
 */
trait MakesDomainData
{
    protected function makeVenue(string $name = 'テスト会場'): Venue
    {
        return Venue::create(['name' => $name]);
    }

    protected function makeEvent(string $name = 'テスト公演', string $date = '2026-08-01', ?Venue $venue = null): Event
    {
        return Event::create([
            'event_name' => $name,
            'event_date' => $date,
            'venue_id' => $venue?->id,
        ]);
    }

    protected function makeAttendance(User $user, ?Event $event = null, string $status = 'attended'): Attendance
    {
        $event ??= $this->makeEvent();

        return Attendance::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'status' => $status,
        ]);
    }

    protected function makeMembership(User $user, ?string $oshiColor = null): FcMembership
    {
        $group = IdentityGroup::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'FC']);
        $person = Person::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => '名義人']);

        return FcMembership::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'A',
            'oshi_color' => $oshiColor,
        ]);
    }
}
