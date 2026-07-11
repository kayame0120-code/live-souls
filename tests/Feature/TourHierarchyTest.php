<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\FcMembership;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * v1.4/v1.5 の要件テスト（指示書v1.4 U1/U4/U6・v1.5 V1/V3）:
 * ツアー階層の表示・カスケード選択の絞り込み・当落のツアー単位グルーピング・
 * 申込登録が pending 自動生成であること・公演名が tours.name 由来であること。
 */
class TourHierarchyTest extends TestCase
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

    public function test_U4_公演一覧はツアーカード_詳細は日程一覧(): void
    {
        $tour = $this->makeTour('Prism Tour');
        $this->makeEvent('Prism Tour', '2026-08-09', $this->makeVenue('東京ドーム'));
        $this->makeEvent('Prism Tour', '2026-08-23', $this->makeVenue('京セラ'));

        // 一覧＝ツアー名と全n公演
        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Prism Tour')
            ->assertSee('全2公演');

        // 詳細＝日程一覧（会場名）
        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee('東京ドーム')
            ->assertSee('京セラ');
    }

    public function test_V1_カスケード_ツアー配下の日程のみ返す(): void
    {
        $tourA = $this->makeTour('ツアーA');
        $tourB = $this->makeTour('ツアーB');
        $eventA = $this->makeEvent('ツアーA', '2026-08-01', $this->makeVenue('会場A'));
        $this->makeEvent('ツアーB', '2026-08-02', $this->makeVenue('会場B'));

        $json = $this->getJson(route('api.tours.events', $tourA))->assertOk()->json();

        // ツアーA配下の日程だけが返る
        $this->assertCount(1, $json);
        $this->assertSame($eventA->id, $json[0]['id']);
        $this->assertStringContainsString('会場A', $json[0]['label']);
    }

    public function test_V3_申込登録はpending自動生成_当落は選ばせない(): void
    {
        $membership = $this->makeMembership($this->user);
        $event = $this->makeEvent('申込ツアー', '2026-09-01');

        // フォームは event_id + 名義のみ。result は送らない
        $this->post(route('lots.store'), [
            'event_id' => $event->id,
            'identity_id' => $membership->id,
        ])->assertRedirect(route('lots.index'));

        $attendance = Attendance::withoutGlobalScopes()->where('event_id', $event->id)->first();
        $this->assertSame('applied', $attendance->status);
        $this->assertSame('pending', $attendance->fcMemberships()->first()->pivot->result);
    }

    public function test_U6_当落一覧はツアー単位_詳細は待ち結果区分(): void
    {
        $membership = $this->makeMembership($this->user);
        $tour = $this->makeTour('当落ツアー');
        $pendingEvent = $this->makeEvent('当落ツアー', '2026-09-10', $this->makeVenue('未発表会場'));
        $wonEvent = $this->makeEvent('当落ツアー', '2026-07-10', $this->makeVenue('当選会場'));

        $a1 = $this->makeAttendance($this->user, $pendingEvent, 'applied');
        $a1->fcMemberships()->attach($membership->id, ['result' => 'pending']);
        $a2 = $this->makeAttendance($this->user, $wonEvent, 'planned');
        $a2->fcMemberships()->attach($membership->id, ['result' => 'won']);

        // 一覧＝ツアーカード（当落待ちあり）
        $this->get(route('lots.index'))
            ->assertOk()
            ->assertSee('当落ツアー')
            ->assertSee('当落待ちあり');

        // 詳細＝待ち/結果の区分と会場
        $this->get(route('lots.tour', $tour))
            ->assertOk()
            ->assertSee('当落待ち')
            ->assertSee('結果')
            ->assertSee('未発表会場')
            ->assertSee('当選会場');
    }

    public function test_V4_公演名見出しはtours_name由来_event_labelを結合(): void
    {
        $event = $this->makeEvent('Aurora Tour', '2026-06-14', $this->makeVenue('大阪城'), '大阪 2日目');
        $attendance = $this->makeAttendance($this->user, $event, 'attended');

        // アクセサ経由の見出し＝tours.name + event_label
        $this->assertSame('Aurora Tour 大阪 2日目', $attendance->event_name);

        // 参戦記録一覧に見出しが出る
        $this->get(route('attendances.index', ['year' => '2026']))
            ->assertOk()
            ->assertSee('Aurora Tour 大阪 2日目');
    }

    public function test_U1_tour逆生成_同名は同一tourに集約(): void
    {
        // makeEvent は同名をツアーに寄せる（移行と同じ集約規則）
        $this->makeEvent('同じツアー', '2026-08-01');
        $this->makeEvent('同じツアー', '2026-08-02');
        $this->makeEvent('別ツアー', '2026-08-03');

        $this->assertSame(2, Tour::count());
        $this->assertSame(2, Event::whereHas('tour', fn ($q) => $q->where('name', '同じツアー'))->count());
        // 全 events に tour_id 非NULL
        $this->assertSame(0, Event::whereNull('tour_id')->count());
    }

    public function test_U2_tour_idなしのevent作成は失敗する(): void
    {
        // NOT NULL 制約（DBレベル）
        $this->expectException(\Illuminate\Database\QueryException::class);
        Event::create(['event_date' => '2026-08-01']);
    }
}
