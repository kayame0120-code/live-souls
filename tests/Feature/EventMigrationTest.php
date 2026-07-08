<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * event_id 移行後の整合性（spec §8 手順3-4・指示書T2・§9テスト）。
 * 移行済みスキーマ上で「参戦の公演名・日付・会場が events 経由で解決できる」ことを突合する。
 */
class EventMigrationTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    public function test_参戦の公演名日付会場がevents経由で一致する(): void
    {
        $user = User::factory()->create();
        $venue = $this->makeVenue('横浜アリーナ');
        $event = $this->makeEvent('Prism of Night', '2026-07-25', $venue);
        $attendance = $this->makeAttendance($user, $event, 'attended');

        $fresh = Attendance::withoutGlobalScopes()->with('event.venue')->find($attendance->id);

        $this->assertSame('Prism of Night', $fresh->event_name);
        $this->assertSame('2026-07-25', $fresh->event_date->format('Y-m-d'));
        $this->assertSame('横浜アリーナ', $fresh->venue->name);
    }

    public function test_同一公演は複数参戦で1件のeventを共有する(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $event = $this->makeEvent('共有公演', '2026-08-01', $this->makeVenue('東京ドーム'));

        $this->makeAttendance($a, $event, 'attended');
        $this->makeAttendance($b, $event, 'attended');

        // 同一 event を共有し、events は増えない
        $this->assertSame(1, Event::count());
        $this->assertSame(2, Attendance::withoutGlobalScopes()->where('event_id', $event->id)->count());
    }

    public function test_attendancesは旧カラムを持たない(): void
    {
        $this->assertFalse(\Schema::hasColumn('attendances', 'event_name'));
        $this->assertFalse(\Schema::hasColumn('attendances', 'event_date'));
        $this->assertFalse(\Schema::hasColumn('attendances', 'venue_id'));
        $this->assertTrue(\Schema::hasColumn('attendances', 'event_id'));
    }
}
