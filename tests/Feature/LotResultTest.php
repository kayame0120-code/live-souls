<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

class LotResultTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;
    private FcMembership $membership;
    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->membership = $this->makeMembership($this->user);
        $this->attendance = $this->makeAttendance($this->user, $this->makeEvent('公演', '2026-08-01'), 'applied');
        $this->attendance->fcMemberships()->attach($this->membership->id, ['result' => 'pending']);
    }

    public function test_自分の当落結果を当選に更新できる(): void
    {
        $pivotId = $this->attendance->fcMemberships()->first()->pivot->id;

        // 更新後は元の画面（当落 or 参戦詳細）へ戻る
        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won'])
            ->assertRedirect()
            ->assertSessionHas('success');

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
        $this->membership->update(['password' => 'e2e:test-cipher-secret-fc-pass']);

        $ig = IdolGroup::create(['name' => 'テスト']);
        $member = GroupMember::create([
            'idol_group_id' => $ig->id, 'name' => 'テスト',
            'color_name' => '赤', 'color_hex' => '#E53935', 'source_type' => '公式', 'sort_order' => 1,
        ]);

        $this->put(route('identities.update', $this->membership), [
            'person_name' => '太郎',
            'group_id' => $this->membership->group_id,
            'group_member_id' => $member->id,
            'fc_password' => '',
        ])->assertRedirect(route('identities.show', $this->membership));

        $this->assertSame('e2e:test-cipher-secret-fc-pass', $this->membership->fresh()->password);
    }
}
