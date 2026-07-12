<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\IdolGroup;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_3階層の遷移(): void
    {
        $group = IdolGroup::create(['name' => 'Snow Man']);
        $tour = Tour::create(['name' => 'Snow Man LIVE TOUR 2026', 'idol_group_id' => $group->id]);
        Event::create(['tour_id' => $tour->id, 'event_date' => '2026-08-15', 'venue_id' => null]);

        $indexResponse = $this->actingAs($this->user)->get(route('events.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Snow Man');

        $toursResponse = $this->actingAs($this->user)->get(route('events.group-tours', $group));
        $toursResponse->assertOk();
        $toursResponse->assertSee('Snow Man LIVE TOUR 2026');

        $detailResponse = $this->actingAs($this->user)->get(route('tours.show', $tour));
        $detailResponse->assertOk();
        $detailResponse->assertSee('08.15');
    }

    public function test_グループ追加で末尾空白は別行にならない(): void
    {
        IdolGroup::create(['name' => 'なにわ男子']);

        $this->actingAs($this->user)->post(route('idol-groups.store'), [
            'name' => 'なにわ男子 ',
        ]);

        $this->assertEquals(1, IdolGroup::where('name', 'なにわ男子')->count());
    }

    public function test_idol_group_idがnullのツアーは未分類カードに出る(): void
    {
        $tour = Tour::create(['name' => '未分類ツアー', 'idol_group_id' => null]);
        Event::create(['tour_id' => $tour->id, 'event_date' => '2026-09-01']);

        $response = $this->actingAs($this->user)->get(route('events.index'));
        $response->assertOk();
        $response->assertSee('未分類');

        $uncatResponse = $this->actingAs($this->user)->get(route('events.uncategorized'));
        $uncatResponse->assertOk();
        $uncatResponse->assertSee('未分類ツアー');
    }

    public function test_ツアー詳細からグループを後付け変更できる(): void
    {
        $group = IdolGroup::create(['name' => 'SixTONES']);
        $tour = Tour::create(['name' => 'テストツアー', 'idol_group_id' => null]);

        $this->actingAs($this->user)->post(route('tours.update-group', $tour), [
            'idol_group_id' => $group->id,
        ]);

        $tour->refresh();
        $this->assertEquals($group->id, $tour->idol_group_id);
    }

    public function test_公演なしのグループはカードに出ない(): void
    {
        $group = IdolGroup::create(['name' => '空グループ']);
        Tour::create(['name' => '日程なしツアー', 'idol_group_id' => $group->id]);

        $response = $this->actingAs($this->user)->get(route('events.index'));
        $response->assertOk();
        $response->assertDontSee('空グループ');
    }
}
