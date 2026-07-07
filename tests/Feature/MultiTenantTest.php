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
        $group = IdentityGroup::withoutGlobalScopes()->create([
            'user_id' => $this->other->id,
            'name' => '他人のグループ',
        ]);

        $person = Person::withoutGlobalScopes()->create([
            'user_id' => $this->other->id,
            'name' => '他人',
        ]);

        $membership = FcMembership::withoutGlobalScopes()->create([
            'user_id' => $this->other->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'テスト',
        ]);

        $this->actingAs($this->user)
            ->get(route('identities.show', $membership))
            ->assertStatus(404);
    }
}
