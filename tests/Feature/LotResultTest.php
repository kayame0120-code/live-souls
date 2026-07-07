<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotResultTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private FcMembership $membership;
    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $group = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'G']);
        $person = Person::create(['user_id' => $this->user->id, 'name' => '太郎']);
        $this->membership = FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'A',
        ]);

        $this->attendance = Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '公演',
            'event_date' => '2026-08-01',
        ]);
        $this->attendance->fcMemberships()->attach($this->membership->id, ['result' => 'pending']);
    }

    public function test_自分の当落結果を当選に更新できる(): void
    {
        $pivotId = $this->attendance->fcMemberships()->first()->pivot->id;

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won'])
            ->assertRedirect(route('attendances.show', $this->attendance));

        $this->assertDatabaseHas('attendance_identity', [
            'id' => $pivotId,
            'result' => 'won',
        ]);
    }

    public function test_不正なresult値は拒否される(): void
    {
        $pivotId = $this->attendance->fcMemberships()->first()->pivot->id;

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'invalid'])
            ->assertSessionHasErrors('result');

        $this->assertDatabaseHas('attendance_identity', [
            'id' => $pivotId,
            'result' => 'pending',
        ]);
    }

    public function test_FCパスワード空送信で既存値が維持される(): void
    {
        $this->membership->update(['password' => 'secret-fc-pass']);

        $this->put(route('identities.update', $this->membership), [
            'person_name' => '太郎',
            'group_id' => $this->membership->group_id,
            'artist_name' => 'A',
            'fc_password' => '',
        ])->assertRedirect(route('identities.show', $this->membership));

        $this->assertSame('secret-fc-pass', $this->membership->fresh()->password);
    }
}
