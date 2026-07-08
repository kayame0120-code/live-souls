<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * 公演共有マスタ events（spec §5・指示書T5-T6・§3テスト）:
 * 削除ガード / 重複警告（ブロックしない）/ 全ユーザー追加可 / 一括インポート。
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

    public function test_参戦0件の公演は削除できる(): void
    {
        $event = $this->makeEvent('削除対象', '2026-09-01');

        $this->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_参戦が紐づく公演は削除できない(): void
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
        $this->makeEvent('昼公演', '2026-09-12', $venue);

        // 未確認の登録 → 警告で差し戻し（登録されない）
        $this->post(route('events.store'), [
            'event_name' => '夜公演',
            'event_date' => '2026-09-12',
            'venue_id' => $venue->id,
        ])->assertSessionHas('duplicate_warning');

        $this->assertDatabaseMissing('events', ['event_name' => '夜公演']);

        // confirm_duplicate=1 で続行 → 登録される（ブロックしない）
        $this->post(route('events.store'), [
            'event_name' => '夜公演',
            'event_date' => '2026-09-12',
            'venue_id' => $venue->id,
            'confirm_duplicate' => 1,
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', ['event_name' => '夜公演', 'venue_id' => $venue->id]);
    }

    public function test_別ユーザーも公演マスタを追加できる(): void
    {
        // events は user_id を持たない共有マスタ。招待済みユーザーなら誰でも追加可
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('events.store'), [
                'event_name' => '他ユーザーの登録公演',
                'event_date' => '2026-10-01',
            ])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', ['event_name' => '他ユーザーの登録公演']);
    }

    public function test_一括インポートは共有マスタに入り名義選択はない(): void
    {
        $this->post(route('events.import.store'), [
            'rows' => [
                ['include' => 1, 'event_name' => '公演A', 'event_date' => '2026-09-12', 'venue_name' => '横浜アリーナ'],
                ['include' => 1, 'event_name' => '公演B', 'event_date' => '2026-09-13', 'venue_name' => '横浜アリーナ'],
                // 必須未充足（event_name空）→ 取込対象外
                ['include' => 1, 'event_name' => '', 'event_date' => '2026-09-14', 'venue_name' => ''],
            ],
        ])->assertRedirect(route('events.index'));

        $this->assertDatabaseHas('events', ['event_name' => '公演A']);
        $this->assertDatabaseHas('events', ['event_name' => '公演B']);
        $this->assertSame(2, Event::count());
        // 同名会場は1件に集約される
        $this->assertSame(1, Venue::where('name', '横浜アリーナ')->count());
    }
}
