<?php

namespace Tests\Feature;

use App\Contracts\LlmService;
use App\Services\Llm\FakeLlmService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_画像5枚を投入して確認画面まで到達する(): void
    {
        $images = [];
        for ($i = 0; $i < 5; $i++) {
            $images[] = UploadedFile::fake()->image("screenshot{$i}.png", 640, 480);
        }

        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'images' => $images,
        ]);

        $response->assertOk();
        $response->assertSee('フェイク会場');
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

    public function test_解析画像はストレージに永続保存されない(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $image = UploadedFile::fake()->image('test.png', 640, 480);

        $this->actingAs($this->user)->post(route('events.import.parse'), [
            'images' => [$image],
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->assertDirectoryEmpty('ai-imports');
    }

    public function test_テキストのみでもAI解析できる(): void
    {
        $response = $this->actingAs($this->user)->post(route('events.import.parse'), [
            'text' => '2026年8月15日 東京ドーム 17:00',
        ]);

        $response->assertOk();
        $response->assertSee('フェイク会場');
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
