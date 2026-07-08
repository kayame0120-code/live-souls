<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * 申込登録・当選昇格・タイムラインのapplied除外（spec §5-7・指示書§3）。
 * v1.2: 公演は events 共有マスタ（event_id）を参照。
 */
class LotFlowTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;
    private FcMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->membership = $this->makeMembership($this->user);
    }

    private function createApplied(): Attendance
    {
        $event = $this->makeEvent('申込公演', now()->addMonth()->format('Y-m-d'));
        $attendance = $this->makeAttendance($this->user, $event, 'applied');
        $attendance->fcMemberships()->attach($this->membership->id, ['result' => 'pending']);

        return $attendance;
    }

    public function test_申込登録でapplied_pendingが作成される(): void
    {
        $event = $this->makeEvent('テスト公演', '2026-09-12');

        $this->post(route('lots.store'), [
            'event_id' => $event->id,
            'identity_ids' => [$this->membership->id],
        ])->assertRedirect(route('lots.index'));

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'status' => 'applied',
        ]);
        $this->assertDatabaseHas('attendance_identity', [
            'fc_membership_id' => $this->membership->id,
            'result' => 'pending',
        ]);
    }

    public function test_申込登録は名義必須(): void
    {
        $event = $this->makeEvent('テスト公演', '2026-09-12');

        $this->post(route('lots.store'), [
            'event_id' => $event->id,
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

        $this->patch(route('attendance-identities.update-result', $pivotId), ['result' => 'pending']);
        $this->assertSame('planned', $attendance->fresh()->status);
    }

    public function test_タイムラインはappliedを表示しない(): void
    {
        $this->makeAttendance($this->user, $this->makeEvent('申込中の公演', '2026-06-01'), 'applied');
        $this->makeAttendance($this->user, $this->makeEvent('参戦予定の公演', '2026-06-02'), 'planned');
        $this->makeAttendance($this->user, $this->makeEvent('参戦済みの公演', '2026-06-03'), 'attended');
        $this->makeAttendance($this->user, $this->makeEvent('スキップの公演', '2026-06-04'), 'skipped');

        $response = $this->get(route('attendances.index', ['year' => '2026']));

        $response->assertOk()
            ->assertDontSee('申込中の公演')
            ->assertSee('参戦予定の公演')
            ->assertSee('参戦済みの公演')
            ->assertSee('スキップの公演');
    }
}
