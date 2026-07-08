<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * v1.2 で作り替えた各画面が Blade コンパイル・描画エラーなく 200 を返すことのスモーク。
 */
class PageRenderSmokeTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->makeMembership($this->user, '#E60033');
        $this->makeEvent('スモーク公演', '2026-09-01', $this->makeVenue('スモーク会場'));
    }

    public static function pageProvider(): array
    {
        return [
            'ホーム' => ['home'],
            '参戦記録' => ['attendances.index'],
            '参戦登録' => ['attendances.create'],
            '名義一覧' => ['identities.index'],
            '名義追加' => ['identities.create'],
            '当落' => ['lots.index'],
            '申込登録' => ['lots.create'],
            '公演一覧' => ['events.index'],
            '公演登録' => ['events.create'],
            '一括インポート' => ['events.import'],
            '招待管理' => ['invitations.index'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_画面が200を返す(string $routeName): void
    {
        $this->get(route($routeName))->assertOk();
    }

    public function test_名義編集と会場詳細も200(): void
    {
        $membership = \App\Models\FcMembership::first();
        $venue = \App\Models\Venue::first();

        $this->get(route('identities.edit', $membership))->assertOk();
        $this->get(route('identities.show', $membership))->assertOk();
        $this->get(route('venues.show', $venue))->assertOk();
    }
}
