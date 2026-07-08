<?php

namespace Tests\Concerns;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;

/**
 * ドメインデータ（tours / events / attendances / 名義）を組み立てるテストヘルパー。
 * v1.4: events は必ず tour 配下（tour_id 必須）。公演見出しは tours.name(+event_label)。
 */
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

    /**
     * 公演（日程）を作成。$name はツアー名として tours に寄せる（同名は同一tour）。
     * event_label は $label（任意）。従来の makeEvent 呼び出しはそのまま動く。
     */
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
