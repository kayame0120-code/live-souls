<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * 公演共有マスタ events / tours（spec §5・v1.4）:
 * 日程削除ガード / 重複警告（ブロックしない）/ 全ユーザー追加可 / 一括インポート（ツアー解決）。
 */
class EventMasterTest extends TestCase
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

    public function test_参戦0件の日程は削除できる(): void
    {
        $event = $this->makeEvent('削除対象', '2026-09-01');

        $this->delete(route('events.destroy', $event))
            ->assertRedirect(route('tours.show', $event->tour));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_参戦が紐づく日程は削除できない(): void
    {
        $event = $this->makeEvent('参戦あり', '2026-09-01');
        $this->makeAttendance($this->user, $event);

        $this->delete(route('events.destroy', $event))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_同一会場同一日付は重複警告が出るがブロックしない(): void
    {
        $venue = $this->makeVenue('横浜アリーナ');
        $tour = Tour::create(['name' => 'テストツアー']);
        $this->makeEvent('テストツアー', '2026-09-12', $venue); // 既存日程（同ツアー）

        // 未確認の登録 → 警告で差し戻し（登録されない）
        $this->post(route('events.store', $tour), [
            'event_date' => '2026-09-12',
            'venue_id' => $venue->id,
        ])->assertSessionHas('duplicate_warning');

        $this->assertSame(1, Event::where('venue_id', $venue->id)->whereDate('event_date', '2026-09-12')->count());

        // confirm_duplicate=1 で続行 → 登録される（ブロックしない・昼夜想定）
        $this->post(route('events.store', $tour), [
            'event_date' => '2026-09-12',
            'venue_id' => $venue->id,
            'confirm_duplicate' => 1,
        ])->assertRedirect(route('tours.show', $tour));

        $this->assertSame(2, Event::where('venue_id', $venue->id)->whereDate('event_date', '2026-09-12')->count());
    }

    public function test_別ユーザーもツアー配下に日程を追加できる(): void
    {
        // tours/events は user_id を持たない共有マスタ。招待済みユーザーなら誰でも追加可
        $other = User::factory()->create();
        $tour = Tour::create(['name' => '共有ツアー']);

        $this->actingAs($other)
            ->post(route('events.store', $tour), [
                'event_date' => '2026-10-01',
            ])
            ->assertRedirect(route('tours.show', $tour));

        $this->assertSame(1, Event::where('tour_id', $tour->id)->whereDate('event_date', '2026-10-01')->count());
    }

    public function test_一括インポートはツアーを解決して日程を登録する(): void
    {
        $this->post(route('events.import.store'), [
            'tour_name' => 'インポートツアー',
            'rows' => [
                ['include' => 1, 'event_date' => '2026-09-12', 'venue_name' => '横浜アリーナ'],
                ['include' => 1, 'event_date' => '2026-09-13', 'venue_name' => '横浜アリーナ'],
                // 必須未充足（event_date空）→ 取込対象外
                ['include' => 1, 'event_date' => '', 'venue_name' => ''],
            ],
        ])->assertRedirect(route('events.index'));

        $tour = Tour::where('name', 'インポートツアー')->first();
        $this->assertNotNull($tour);
        $this->assertSame(2, Event::where('tour_id', $tour->id)->count());
        // 同名会場は1件に集約
        $this->assertSame(1, Venue::where('name', '横浜アリーナ')->count());
    }

    public function test_一括インポートは既存ツアーに紐付ける(): void
    {
        $existing = Tour::create(['name' => '既存ツアー']);

        $this->post(route('events.import.store'), [
            'tour_name' => '既存ツアー',
            'rows' => [
                ['include' => 1, 'event_date' => '2026-09-12', 'venue_name' => '東京ドーム'],
            ],
        ])->assertRedirect(route('events.index'));

        // 新規 tour は作られず、既存に紐づく
        $this->assertSame(1, Tour::where('name', '既存ツアー')->count());
        $this->assertSame(1, Event::where('tour_id', $existing->id)->count());
    }
}
