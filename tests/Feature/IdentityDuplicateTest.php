<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private IdolGroup $igSnowMan;
    private IdolGroup $igSixTONES;
    private FcMembership $source;
    private GroupMember $memberA;
    private GroupMember $memberB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'test', 'email' => 'test@example.com', 'password' => 'password']);

        $this->igSnowMan = IdolGroup::create(['name' => 'Snow Man']);
        $this->memberA = GroupMember::create([
            'idol_group_id' => $this->igSnowMan->id, 'name' => '目黒蓮',
            'color_name' => '黒', 'color_hex' => '#212121', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $this->igSixTONES = IdolGroup::create(['name' => 'SixTONES']);
        $this->memberB = GroupMember::create([
            'idol_group_id' => $this->igSixTONES->id, 'name' => 'ジェシー',
            'color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $person = Person::create([
            'user_id' => $this->user->id, 'name' => '田中太郎',
            'birth_date' => '1990-01-15', 'phone' => 'e2e:test-phone', 'address' => 'e2e:test-address',
        ]);
        $this->source = FcMembership::create([
            'user_id' => $this->user->id, 'person_id' => $person->id, 'group_id' => $this->igSnowMan->id,
            'artist_name' => '目黒蓮', 'member_no' => 'e2e:test-cipher-SM001', 'oshi_color' => '#212121',
            'group_member_id' => $this->memberA->id,
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
            'group_id' => $this->igSixTONES->id,
            'group_member_id' => $this->memberB->id,
            'oshi_color' => '#E53935',
        ]);

        $this->assertSame($beforeCount, Person::count());
        $this->assertSame(2, FcMembership::count());

        $dup = FcMembership::where('artist_name', 'ジェシー')->first();
        $this->assertNotNull($dup);
        $this->assertSame($this->source->person_id, $dup->person_id);
    }

    public function test_担当メンバー選択でgroup_member_idとoshi_colorとartist_nameが紐づく(): void
    {
        $this->actingAs($this->user)->post(route('identities.store-duplicate', $this->source), [
            'group_id' => $this->igSnowMan->id,
            'group_member_id' => $this->memberA->id,
            'oshi_color' => '#212121',
        ]);

        $dup = FcMembership::latest('id')->first();
        $this->assertSame($this->memberA->id, $dup->group_member_id);
        $this->assertSame('#212121', $dup->oshi_color);
        $this->assertSame('目黒蓮', $dup->artist_name);
    }

    public function test_担当メンバー選択は名義編集でもartist_nameが自動導出される(): void
    {
        $this->actingAs($this->user)->put(route('identities.update', $this->source), [
            'person_name' => '田中太郎',
            'group_id' => $this->igSixTONES->id,
            'group_member_id' => $this->memberB->id,
            'oshi_color' => '#E53935',
        ]);

        $this->source->refresh();
        $this->assertSame($this->memberB->id, $this->source->group_member_id);
        $this->assertSame('#E53935', $this->source->oshi_color);
        $this->assertSame('ジェシー', $this->source->artist_name);
    }

    public function test_group_member_id未選択はバリデーションエラー(): void
    {
        $this->actingAs($this->user)->post(route('identities.store-duplicate', $this->source), [
            'group_id' => $this->igSnowMan->id,
        ])->assertSessionHasErrors('group_member_id');
    }
}
