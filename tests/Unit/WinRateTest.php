<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WinRateTest extends TestCase
{
    use RefreshDatabase;

    private FcMembership $membership;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $group = IdentityGroup::create([
            'user_id' => $this->user->id,
            'name' => 'テストグループ',
        ]);

        $person = Person::create([
            'user_id' => $this->user->id,
            'name' => 'テスト太郎',
        ]);

        $this->membership = FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'テストアーティスト',
        ]);
    }

    public function test_申込0件のとき当選率はnull(): void
    {
        $this->assertSame(0, $this->membership->applicationCount());
        $this->assertNull($this->membership->winRate());
    }

    public function test_全当選で100パーセント(): void
    {
        $this->createAttendanceWithResult('won');
        $this->createAttendanceWithResult('won');

        $this->assertSame(2, $this->membership->applicationCount());
        $this->assertSame(2, $this->membership->winCount());
        $this->assertEqualsWithDelta(1.0, $this->membership->winRate(), 0.001);
    }

    public function test_全落選で0パーセント(): void
    {
        $this->createAttendanceWithResult('lost');
        $this->createAttendanceWithResult('lost');

        $this->assertSame(2, $this->membership->applicationCount());
        $this->assertSame(0, $this->membership->winCount());
        $this->assertEqualsWithDelta(0.0, $this->membership->winRate(), 0.001);
    }

    public function test_混在の当選率(): void
    {
        $this->createAttendanceWithResult('won');
        $this->createAttendanceWithResult('lost');
        $this->createAttendanceWithResult('pending');

        $this->assertSame(3, $this->membership->applicationCount());
        $this->assertSame(1, $this->membership->winCount());
        $this->assertEqualsWithDelta(1 / 3, $this->membership->winRate(), 0.001);
    }

    private function createAttendanceWithResult(string $result): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => 'テスト公演',
            'event_date' => now()->format('Y-m-d'),
        ]);

        $attendance->fcMemberships()->attach($this->membership->id, [
            'result' => $result,
        ]);
    }
}
