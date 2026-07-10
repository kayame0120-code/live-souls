<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;


use App\Models\Person;
use App\Models\User;
use App\Models\VenueNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    public function test_他ユーザーの参戦記録にアクセスすると404(): void
    {
        $attendance = $this->makeAttendance($this->other);

        $this->actingAs($this->user)
            ->get(route('attendances.show', $attendance))
            ->assertStatus(404);
    }

    public function test_他ユーザーの参戦記録を更新できない(): void
    {
        $attendance = $this->makeAttendance($this->other);
        $event = $this->makeEvent('自分の公演', '2026-07-01');

        $this->actingAs($this->user)
            ->put(route('attendances.update', $attendance), [
                'event_id' => $event->id,
                'status' => 'attended',
            ])
            ->assertStatus(404);
    }

    public function test_他ユーザーの参戦記録を削除できない(): void
    {
        $attendance = $this->makeAttendance($this->other);

        $this->actingAs($this->user)
            ->delete(route('attendances.destroy', $attendance))
            ->assertStatus(404);
    }

    public function test_他ユーザーの会場メモは参照されない(): void
    {
        $venue = $this->makeVenue();

        VenueNote::create([
            'user_id' => $this->other->id,
            'venue_id' => $venue->id,
            'lodging' => '秘密のホテル',
        ]);

        $this->actingAs($this->user)
            ->get(route('venues.show', $venue))
            ->assertOk()
            ->assertDontSee('秘密のホテル');
    }

    public function test_他ユーザーの名義にアクセスすると404(): void
    {
        $membership = $this->makeMembership($this->other);

        $this->actingAs($this->user)
            ->get(route('identities.show', $membership))
            ->assertStatus(404);
    }

    public function test_他ユーザーの名義IDでは参戦登録できない(): void
    {
        $membership = $this->makeMembership($this->other);
        $event = $this->makeEvent();

        $this->actingAs($this->user)
            ->post(route('attendances.store'), [
                'event_id' => $event->id,
                'status' => 'attended',
                'identity_ids' => [$membership->id],
            ])
            ->assertSessionHasErrors('identity_ids.0');
    }

    public function test_存在しないグループIDでは名義登録できない(): void
    {
        $this->actingAs($this->user)
            ->post(route('identities.store'), [
                'person_name' => 'テスト太郎',
                'group_id' => 99999,
                'group_member_id' => 99999,
            ])
            ->assertSessionHasErrors('group_id');
    }

    public function test_他ユーザーの当落結果は更新できない(): void
    {
        $membership = $this->makeMembership($this->other);
        $attendance = $this->makeAttendance($this->other, null, 'applied');
        $attendance->fcMemberships()->attach($membership->id, ['result' => 'pending']);
        $pivotId = $attendance->fcMemberships()->withoutGlobalScopes()->first()->pivot->id;

        $this->actingAs($this->user)
            ->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won'])
            ->assertStatus(404);
    }
}
