<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use App\Models\VenueNote;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

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
        $attendance = Attendance::create([
            'user_id' => $this->other->id,
            'event_name' => '他人の公演',
            'event_date' => '2026-07-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('attendances.show', $attendance))
            ->assertStatus(404);
    }

    public function test_他ユーザーの参戦記録を更新できない(): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->other->id,
            'event_name' => '他人の公演',
            'event_date' => '2026-07-01',
        ]);

        $this->actingAs($this->user)
            ->put(route('attendances.update', $attendance), [
                'event_name' => '上書き',
                'event_date' => '2026-07-01',
                'status' => 'attended',
            ])
            ->assertStatus(404);
    }

    public function test_他ユーザーの参戦記録を削除できない(): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->other->id,
            'event_name' => '他人の公演',
            'event_date' => '2026-07-01',
        ]);

        $this->actingAs($this->user)
            ->delete(route('attendances.destroy', $attendance))
            ->assertStatus(404);
    }

    public function test_他ユーザーの会場メモは参照されない(): void
    {
        $venue = Venue::create(['name' => 'テスト会場']);

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
        $membership = $this->createMembershipFor($this->other);

        $this->actingAs($this->user)
            ->get(route('identities.show', $membership))
            ->assertStatus(404);
    }

    public function test_他ユーザーの名義IDでは参戦登録できない(): void
    {
        $membership = $this->createMembershipFor($this->other);

        $this->actingAs($this->user)
            ->post(route('attendances.store'), [
                'event_name' => 'テスト公演',
                'event_date' => '2026-07-01',
                'status' => 'attended',
                'identity_ids' => [$membership->id],
            ])
            ->assertSessionHasErrors('identity_ids.0');
    }

    public function test_他ユーザーのグループIDでは名義登録できない(): void
    {
        $otherGroup = IdentityGroup::withoutGlobalScopes()->create([
            'user_id' => $this->other->id,
            'name' => '他人のグループ',
        ]);

        $this->actingAs($this->user)
            ->post(route('identities.store'), [
                'person_name' => 'テスト太郎',
                'group_id' => $otherGroup->id,
                'artist_name' => 'テスト',
            ])
            ->assertSessionHasErrors('group_id');
    }

    public function test_他ユーザーの当落結果は更新できない(): void
    {
        $membership = $this->createMembershipFor($this->other);

        $attendance = Attendance::withoutGlobalScopes()->create([
            'user_id' => $this->other->id,
            'event_name' => '他人の公演',
            'event_date' => '2026-07-01',
        ]);
        $attendance->fcMemberships()->attach($membership->id, ['result' => 'pending']);
        $pivotId = $attendance->fcMemberships()->withoutGlobalScopes()->first()->pivot->id;

        $this->actingAs($this->user)
            ->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won'])
            ->assertStatus(404);
    }

    /** 指定ユーザーの名義（グループ・名義人込み）を作成するヘルパー */
    private function createMembershipFor(User $owner): FcMembership
    {
        $group = IdentityGroup::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'name' => 'グループ',
        ]);

        $person = Person::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'name' => '名義人',
        ]);

        return FcMembership::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'テスト',
        ]);
    }
}
