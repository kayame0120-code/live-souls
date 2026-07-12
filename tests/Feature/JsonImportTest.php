<?php

namespace Tests\Feature;

use App\Models\Setlist;
use App\Models\SetlistItem;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class JsonImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_eventsとsetlistを同時にアップロードして確認画面に並ぶ(): void
    {
        $eventsJson = json_encode([
            'tour' => 'テストツアー',
            'events' => [
                ['event_date' => '2026-08-15', 'start_time' => '17:00', 'venue' => '東京ドーム'],
            ],
        ]);
        $setlistJson = json_encode([
            'tour' => 'テストツアー',
            'items' => [
                ['order' => 1, 'title' => '曲A', 'note' => null],
                ['order' => 2, 'title' => '曲B', 'note' => 'アンコール'],
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_files' => [
                UploadedFile::fake()->createWithContent('events.json', $eventsJson),
                UploadedFile::fake()->createWithContent('setlist.json', $setlistJson),
            ],
        ]);

        $response->assertOk();
        $response->assertSee('東京ドーム');
        $response->assertSee('曲A');
        $response->assertSee('曲B');
        $response->assertSee('1公演');
        $response->assertSee('2曲');
    }

    public function test_setlistJSON単体で統合窓口から登録できる(): void
    {
        $setlistJson = json_encode([
            'tour' => '単体ツアー',
            'items' => [
                ['order' => 1, 'title' => '曲1', 'note' => null],
                ['order' => 2, 'title' => '曲2', 'note' => null],
                ['order' => 3, 'title' => '曲3', 'note' => null],
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_files' => [
                UploadedFile::fake()->createWithContent('setlist.json', $setlistJson),
            ],
        ]);

        $response->assertOk();
        $response->assertSee('曲1');
        $response->assertSee('3曲');

        $storeResponse = $this->actingAs($this->user)->post(route('events.import.json.store'), [
            'setlist_groups' => [
                [
                    'tour_name' => '単体ツアー',
                    'items' => [
                        ['include' => '1', 'title' => '曲1', 'display_label' => ''],
                        ['include' => '1', 'title' => '曲2', 'display_label' => ''],
                        ['include' => '1', 'title' => '曲3', 'display_label' => ''],
                    ],
                ],
            ],
        ]);

        $storeResponse->assertRedirect(route('events.index'));

        $tour = Tour::where('name', '単体ツアー')->first();
        $this->assertNotNull($tour);
        $setlist = Setlist::where('tour_id', $tour->id)->first();
        $this->assertNotNull($setlist);
        $this->assertCount(3, $setlist->items);
    }

    public function test_eventsとsetlistを同時にstoreで両方登録できる(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.json.store'), [
            'events_groups' => [
                [
                    'tour_name' => '同時登録ツアー',
                    'events' => [
                        ['include' => '1', 'event_date' => '2026-08-15', 'start_time' => '17:00', 'venue_name' => '東京ドーム', 'event_label' => ''],
                    ],
                    'deadlines' => [],
                ],
            ],
            'setlist_groups' => [
                [
                    'tour_name' => '同時登録ツアー',
                    'items' => [
                        ['include' => '1', 'title' => '曲X', 'display_label' => ''],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success');

        $tour = Tour::where('name', '同時登録ツアー')->first();
        $this->assertNotNull($tour);
        $this->assertCount(1, $tour->events);
        $this->assertCount(1, $tour->setlists);
    }

    public function test_必須キー欠落JSONは500ではなく日本語エラーが返る(): void
    {
        $eventsJson = json_encode([
            'tour' => '正常ツアー',
            'events' => [['event_date' => '2026-08-01', 'venue' => '会場']],
        ]);
        $invalidJson = json_encode(['nothing' => 'useful']);

        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_files' => [
                UploadedFile::fake()->createWithContent('ok.json', $eventsJson),
                UploadedFile::fake()->createWithContent('bad.json', $invalidJson),
            ],
        ]);

        $response->assertOk();
        $response->assertSee('スキップ');
        $response->assertSee('bad.json');
    }

    public function test_型不一致JSONはバリデーションエラーが返る(): void
    {
        $invalidJson = json_encode([
            'tour' => 'テスト',
            'events' => [
                ['event_date' => 'not-a-date', 'venue' => '会場'],
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_text' => $invalidJson,
        ]);

        $response->assertOk();
        $response->assertSee('有効な日付ではありません');
    }

    public function test_存在しないevent_id参照のsetlistはstoreでエラー(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.json.store'), [
            'setlist_groups' => [
                [
                    'tour_name' => '',
                    'items' => [
                        ['include' => '1', 'title' => '曲Z', 'display_label' => ''],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('events.import'));
        $response->assertSessionHas('error');
    }

    public function test_壊れたJSONファイルは500ではなくエラーメッセージ(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_files' => [
                UploadedFile::fake()->createWithContent('broken.json', '{broken json'),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_setlist_JSONでtitle空は確認画面でバリデーションエラー(): void
    {
        $json = json_encode([
            'tour' => 'テスト',
            'items' => [
                ['order' => 1, 'title' => '', 'note' => null],
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('events.import.json'), [
            'json_text' => $json,
        ]);

        $response->assertOk();
        $response->assertSee('曲名');
    }

    public function test_確認画面経由のstore経路が正常に動作する(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.json.store'), [
            'events_groups' => [
                [
                    'tour_name' => 'ダイレクトテスト',
                    'events' => [
                        ['include' => '1', 'event_date' => '2026-09-01', 'venue_name' => 'テスト会場'],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('events.index'));

        $tour = Tour::where('name', 'ダイレクトテスト')->first();
        $this->assertNotNull($tour);
        $this->assertCount(1, $tour->events);
        $this->assertEquals('2026-09-01', $tour->events->first()->event_date->format('Y-m-d'));
    }
}
