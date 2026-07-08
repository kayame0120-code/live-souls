<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * グループ削除ガード（spec §7 テスト化必須・E1=A案）。
 */
class GroupDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_配下名義があるグループは削除拒否(): void
    {
        $group = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'FC-A']);
        $person = Person::create(['user_id' => $this->user->id, 'name' => '太郎']);
        FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'A',
        ]);

        $this->delete(route('identity-groups.destroy', $group))
            ->assertSessionHas('error', '先に名義を削除または移動してください');

        $this->assertDatabaseHas('identity_groups', ['id' => $group->id]);
    }

    public function test_配下名義が0件のグループは削除成功(): void
    {
        $group = IdentityGroup::create(['user_id' => $this->user->id, 'name' => '空のFC']);

        $this->delete(route('identity-groups.destroy', $group))
            ->assertRedirect(route('identity-groups.index'));

        $this->assertDatabaseMissing('identity_groups', ['id' => $group->id]);
    }
}
