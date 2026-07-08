<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 申込登録・当選昇格・タイムラインのapplied除外（spec §5-7・指示書§3）。
 */
class LotFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private FcMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $group = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'FC']);
        $person = Person::create(['user_id' => $this->user->id, 'name' => '太郎']);
        $this->membership = FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'A',
        ]);
    }

    private function createApplied(): Attendance
    {
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '申込公演',
            'event_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'applied',
        ]);
        $attendance->fcMemberships()->attach($this->membership->id, ['result' => 'pending']);

        return $attendance;
    }

    public function test_申込登録でapplied_pendingが作成される(): void
    {
        $this->post(route('lots.store'), [
            'event_name' => 'テスト公演',
            'event_date' => '2026-09-12',
            'identity_ids' => [$this->membership->id],
        ])->assertRedirect(route('lots.index'));

        $this->assertDatabaseHas('attendances', [
            'event_name' => 'テスト公演',
            'status' => 'applied',
        ]);
        $this->assertDatabaseHas('attendance_identity', [
            'fc_membership_id' => $this->membership->id,
            'result' => 'pending',
        ]);
    }

    public function test_申込登録は名義必須(): void
    {
        $this->post(route('lots.store'), [
            'event_name' => 'テスト公演',
            'event_date' => '2026-09-12',
            'identity_ids' => [],
        ])->assertSessionHasErrors(['identity_ids' => '申込名義を選択してください']);
    }

    public function test_won1件でappliedからplannedに自動昇格(): void
    {
        $attendance = $this->createApplied();
        $pivotId = $attendance->fcMemberships()->first()->pivot->id;

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won']);

        $this->assertSame('planned', $attendance->fresh()->status);
    }

    public function test_全lostならappliedのまま(): void
    {
        $attendance = $this->createApplied();
        $pivotId = $attendance->fcMemberships()->first()->pivot->id;

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'lost']);

        $this->assertSame('applied', $attendance->fresh()->status);
    }

    public function test_wonを取り消しても自動降格しない(): void
    {
        $attendance = $this->createApplied();
        $pivotId = $attendance->fcMemberships()->first()->pivot->id;

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'won']);
        $this->assertSame('planned', $attendance->fresh()->status);

        // 誤入力訂正で pending に戻しても status は planned のまま（spec §5-7-3）
        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'pending']);
        $this->assertSame('planned', $attendance->fresh()->status);
    }

    public function test_タイムラインはappliedを表示しない(): void
    {
        Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '申込中の公演',
            'event_date' => '2026-06-01',
            'status' => 'applied',
        ]);
        Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '参戦予定の公演',
            'event_date' => '2026-06-02',
            'status' => 'planned',
        ]);
        Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '参戦済みの公演',
            'event_date' => '2026-06-03',
            'status' => 'attended',
        ]);
        Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => 'スキップの公演',
            'event_date' => '2026-06-04',
            'status' => 'skipped',
        ]);

        $response = $this->get(route('attendances.index', ['year' => '2026']));

        $response->assertOk()
            ->assertDontSee('申込中の公演')
            ->assertSee('参戦予定の公演')
            ->assertSee('参戦済みの公演')
            ->assertSee('スキップの公演');
    }
}
