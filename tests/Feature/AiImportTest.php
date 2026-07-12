<?php

namespace Tests\Feature;

use App\Contracts\LlmService;
use App\Jobs\ParseWithLlm;
use App\Services\Llm\FakeLlmService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private FakeLlmService $fakeLlm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->fakeLlm = new FakeLlmService();
        $this->app->instance(LlmService::class, $this->fakeLlm);
    }

    public function test_画像5枚を投入するとジョブがキューに積まれ待機画面が返る(): void
    {
        Queue::fake();

        $images = [];
        for ($i = 0; $i < 5; $i++) {
            $images[] = UploadedFile::fake()->image("screenshot{$i}.png", 640, 480);
        }

        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'images' => $images,
        ]);

        $response->assertOk();
        $response->assertSee('AI解析中');
        Queue::assertPushed(ParseWithLlm::class);
    }

    public function test_6枚目は拒否される(): void
    {
        $images = [];
        for ($i = 0; $i < 6; $i++) {
            $images[] = UploadedFile::fake()->image("screenshot{$i}.png", 640, 480);
        }

        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'images' => $images,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('images');
    }

    public function test_ジョブ完了後にポーリングで結果を取得できる(): void
    {
        $cacheKey = 'llm-parse:test-uuid';
        Cache::put($cacheKey, [
            'status' => 'completed',
            'result' => [
                'tour' => 'テストツアー',
                'events' => [['event_date' => '2026-08-15', 'venue' => '東京ドーム', 'start_time' => '17:00', 'event_label' => null]],
                'deadlines' => [],
            ],
        ], now()->addHour());

        $response = $this->actingAs($this->user)->postJson(route('events.import.poll'), [
            'cache_key' => $cacheKey,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'completed']);
        $this->assertArrayHasKey('result', $response->json());
    }

    public function test_ジョブ失敗時にエラーステータスが返る(): void
    {
        $cacheKey = 'llm-parse:test-fail';
        Cache::put($cacheKey, [
            'status' => 'failed',
            'error' => 'AI解析に失敗しました',
        ], now()->addHour());

        $response = $this->actingAs($this->user)->postJson(route('events.import.poll'), [
            'cache_key' => $cacheKey,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'failed', 'error' => 'AI解析に失敗しました']);
    }

    public function test_テキストのみでもジョブが投入される(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'text' => '2026年8月15日 東京ドーム 17:00',
        ]);

        $response->assertOk();
        $response->assertSee('AI解析中');
        Queue::assertPushed(ParseWithLlm::class, function ($job) {
            return true;
        });
    }

    public function test_画像もテキストもなければエラー(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.parse'), []);

        $response->assertRedirect();
        $response->assertSessionHas('error', '画像またはテキストを入力してください');
    }

    public function test_heic画像は拒否される(): void
    {
        $image = UploadedFile::fake()->create('photo.heic', 500, 'image/heic');

        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'images' => [$image],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('images.0');
    }
}
