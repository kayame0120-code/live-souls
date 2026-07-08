<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * 参戦・申込の削除規則（spec §7 Q3・テスト化必須）:
 * won無し（applied / 全pending・lost / 一般参戦）は削除可・写真実体も削除。
 * won付き（昇格済み）は削除不可。
 */
class AttendanceDeleteTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;
    private FcMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->membership = $this->makeMembership($this->user);
    }

    private function makeAttendanceWithResult(string $status, ?string $result = null): Attendance
    {
        $attendance = $this->makeAttendance($this->user, $this->makeEvent('公演', '2026-06-01'), $status);
        if ($result !== null) {
            $attendance->fcMemberships()->attach($this->membership->id, ['result' => $result]);
        }
        return $attendance;
    }

    public function test_applied申込は削除できる(): void
    {
        $attendance = $this->makeAttendanceWithResult('applied', 'pending');

        $this->delete(route('attendances.destroy', $attendance))
            ->assertRedirect(route('attendances.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('attendance_identity', ['attendance_id' => $attendance->id]);
    }

    public function test_全lostの参戦は削除できる(): void
    {
        $attendance = $this->makeAttendanceWithResult('applied', 'lost');

        $this->delete(route('attendances.destroy', $attendance))
            ->assertRedirect(route('attendances.index'));

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    public function test_pivot無しの一般参戦は削除できる(): void
    {
        $attendance = $this->makeAttendanceWithResult('attended');

        $this->delete(route('attendances.destroy', $attendance))
            ->assertRedirect(route('attendances.index'));

        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    public function test_won付き参戦は削除できない(): void
    {
        $attendance = $this->makeAttendanceWithResult('planned', 'won');

        $this->delete(route('attendances.destroy', $attendance))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseHas('attendance_identity', [
            'attendance_id' => $attendance->id,
            'result' => 'won',
        ]);
    }

    public function test_削除時にストレージの写真実体も削除される(): void
    {
        $attendance = $this->makeAttendanceWithResult('attended');

        $photo = app(PhotoService::class)->store(
            $attendance,
            UploadedFile::fake()->image('p.jpg'),
        );
        Storage::disk('local')->assertExists($photo->path);

        $this->delete(route('attendances.destroy', $attendance))
            ->assertRedirect(route('attendances.index'));

        Storage::disk('local')->assertMissing($photo->path);
        $this->assertDatabaseMissing('attendance_photos', ['id' => $photo->id]);
    }
}
