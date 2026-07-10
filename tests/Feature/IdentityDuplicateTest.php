<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private IdentityGroup $group1;
    private IdentityGroup $group2;
    private FcMembership $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'test', 'email' => 'test@example.com', 'password' => 'password']);
        $this->group1 = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'FC A', 'sort_order' => 1]);
        $this->group2 = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'FC B', 'sort_order' => 2]);

        $person = Person::create([
            'user_id' => $this->user->id, 'name' => '田中太郎',
            'birth_date' => '1990-01-15', 'phone' => '090-1234-5678', 'address' => '東京都渋谷区',
        ]);
        $this->source = FcMembership::create([
            'user_id' => $this->user->id, 'person_id' => $person->id, 'group_id' => $this->group1->id,
            'artist_name' => 'Snow Man', 'member_no' => 'SM-001', 'oshi_color' => '#E60033',
        ]);
    }

    public function test_複製画面が表示される(): void
    {
        $response = $this->actingAs($this->user)->get(route('identities.duplicate', $this->source));
        $response->assertOk();
        $response->assertSee('名義を複製');
        $response->assertSee('田中太郎');
    }

    public function test_複製後もpersonsが重複しない(): void
    {
        $beforeCount = Person::count();

        $this->actingAs($this->user)->post(route('identities.store-duplicate', $this->source), [
            'group_id' => $this->group2->id,
            'artist_name' => 'SixTONES',
        ]);

        $this->assertSame($beforeCount, Person::count());
        $this->assertSame(2, FcMembership::count());

        $dup = FcMembership::where('artist_name', 'SixTONES')->first();
        $this->assertSame($this->source->person_id, $dup->person_id);
    }

    public function test_担当メンバー選択でgroup_member_idとoshi_colorが紐づく(): void
    {
        $ig = IdolGroup::create(['name' => 'Snow Man']);
        $member = GroupMember::create([
            'idol_group_id' => $ig->id, 'name' => '目黒蓮',
            'color_name' => '黒', 'color_hex' => '#212121', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $this->actingAs($this->user)->post(route('identities.store-duplicate', $this->source), [
            'group_id' => $this->group2->id,
            'artist_name' => 'Snow Man',
            'group_member_id' => $member->id,
            'oshi_color' => '#212121',
        ]);

        $dup = FcMembership::where('group_id', $this->group2->id)->first();
        $this->assertSame($member->id, $dup->group_member_id);
        $this->assertSame('#212121', $dup->oshi_color);
    }

    public function test_担当メンバー選択は名義編集でも機能する(): void
    {
        $ig = IdolGroup::create(['name' => 'SixTONES']);
        $member = GroupMember::create([
            'idol_group_id' => $ig->id, 'name' => 'ジェシー',
            'color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $this->actingAs($this->user)->put(route('identities.update', $this->source), [
            'person_name' => '田中太郎',
            'group_id' => $this->group1->id,
            'artist_name' => 'SixTONES',
            'group_member_id' => $member->id,
            'oshi_color' => '#E53935',
        ]);

        $this->source->refresh();
        $this->assertSame($member->id, $this->source->group_member_id);
        $this->assertSame('#E53935', $this->source->oshi_color);
    }
}
