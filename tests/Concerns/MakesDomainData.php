<?php

namespace Tests\Concerns;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\Person;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;

trait MakesDomainData
{
    protected function makeVenue(string $name = 'テスト会場'): Venue
    {
        return Venue::create(['name' => $name]);
    }

    protected function makeTour(string $name = 'テストツアー'): Tour
    {
        return Tour::firstOrCreate(['name' => $name]);
    }

    protected function makeEvent(string $name = 'テスト公演', string $date = '2026-08-01', ?Venue $venue = null, ?string $label = null): Event
    {
        $tour = $this->makeTour($name);

        return Event::create([
            'tour_id' => $tour->id,
            'event_label' => $label,
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
        $idolGroup = IdolGroup::firstOrCreate(['name' => 'テストグループ']);
        $member = GroupMember::firstOrCreate(
            ['idol_group_id' => $idolGroup->id, 'name' => 'テストメンバー'],
            ['color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1],
        );
        $person = Person::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => '名義人']);

        return FcMembership::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'group_id' => $idolGroup->id,
            'group_member_id' => $member->id,
            'artist_name' => $member->name,
            'oshi_color' => $oshiColor ?? $member->color_hex,
        ]);
    }
}
