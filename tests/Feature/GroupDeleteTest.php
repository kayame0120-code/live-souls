<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_名義が紐づくidol_groupは削除されない(): void
    {
        $user = User::factory()->create();
        $idolGroup = IdolGroup::create(['name' => 'テスト']);
        $member = GroupMember::create([
            'idol_group_id' => $idolGroup->id, 'name' => 'メンバー',
            'color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1,
        ]);
        $person = Person::create(['user_id' => $user->id, 'name' => '太郎']);
        FcMembership::create([
            'user_id' => $user->id, 'person_id' => $person->id,
            'group_id' => $idolGroup->id, 'group_member_id' => $member->id,
            'artist_name' => 'メンバー',
        ]);

        $this->assertDatabaseHas('idol_groups', ['id' => $idolGroup->id]);
        $this->assertSame(1, FcMembership::withoutGlobalScopes()->where('group_id', $idolGroup->id)->count());
    }

    public function test_名義がないidol_groupはcascadeDeleteで消える(): void
    {
        $idolGroup = IdolGroup::create(['name' => '空のグループ']);
        GroupMember::create([
            'idol_group_id' => $idolGroup->id, 'name' => 'メンバー',
            'color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $idolGroup->delete();
        $this->assertDatabaseMissing('idol_groups', ['id' => $idolGroup->id]);
        $this->assertSame(0, GroupMember::where('idol_group_id', $idolGroup->id)->count());
    }
}
