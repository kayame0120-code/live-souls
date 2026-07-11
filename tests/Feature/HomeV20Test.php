<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\User;
use App\Models\Venue;
use App\Services\HomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * v2.0: ホーム画面の更新通知カード・チケット確認通知。
 */
class HomeV20Test extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_更新期間中の名義がホームに表示される(): void
    {
        $m = $this->makeMembershipWithJoinedOn('2024-10-01');

        // 更新受付期間: 8/2〜9/30 → 8月15日は対象
        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('更新期間の名義');
        $response->assertSee($m->displayName());
    }

    public function test_更新期間外の名義は表示されない(): void
    {
        $this->makeMembershipWithJoinedOn('2024-10-01');

        // 7月は更新受付期間外（受付は8/2から）
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertDontSee('更新期間の名義');
    }

    public function test_joined_onがnullの名義は更新通知に出ない(): void
    {
        $this->makeMembership($this->user);

        Carbon::setTestNow(Carbon::parse('2026-08-15'));

        $service = app(HomeService::class);
        $this->assertCount(0, $service->getRenewalMemberships());
    }

    public function test_7日以内の参戦予定にチケット確認が表示される(): void
    {
        $venue = $this->makeVenue();
        $event = $this->makeEvent('チケット確認公演', '2026-08-05', $venue);
        $att = $this->makeAttendance($this->user, $event, 'planned');

        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertSee('チケット確認はお済みですか？');
    }

    public function test_8日以上先の参戦予定にはチケット確認が出ない(): void
    {
        $venue = $this->makeVenue();
        $event = $this->makeEvent('遠い公演', '2026-08-20', $venue);
        $this->makeAttendance($this->user, $event, 'planned');

        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $response = $this->get(route('home'));
        $response->assertOk();
        $response->assertDontSee('チケット確認はお済みですか？');
    }

    public function test_attended済みの参戦にはチケット確認が出ない(): void
    {
        $venue = $this->makeVenue();
        $event = $this->makeEvent('確定済み公演', '2026-08-03', $venue);
        $this->makeAttendance($this->user, $event, 'attended');

        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $service = app(HomeService::class);
        $this->assertCount(0, $service->getTicketReminders());
    }

    private function makeMembershipWithJoinedOn(string $joinedOn): FcMembership
    {
        $m = $this->makeMembership($this->user, '#E60033');
        $m->update(['joined_on' => $joinedOn]);
        return $m->fresh();
    }
}
