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
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('identities.show', $membership))->assertOk();
        $this->get(route('venues.show', $venue))->assertOk();
    }

    public function test_ツアー詳細と日程登録も200(): void
    {
        // v1.4: ツアー配下の画面（tour param 必須）
        $tour = \App\Models\Event::first()->tour;
        $this->get(route('tours.show', $tour))->assertOk();
        $this->get(route('events.create', $tour))->assertOk();
    }

    public function test_参戦詳細_参戦編集_当落カードが200(): void
    {
        // 参戦詳細（att-hero/d-block）・編集・当落（lot-select）の描画を担保
        $membership = \App\Models\FcMembership::first();
        $event = $this->makeEvent('描画確認公演', now()->subDay()->format('Y-m-d'), \App\Models\Venue::first());
        $attendance = $this->makeAttendance($this->user, $event, 'attended');
        $attendance->fcMemberships()->attach($membership->id, ['result' => 'pending']);

        $this->get(route('attendances.show', $attendance))->assertOk();
        $this->get(route('attendances.edit', $attendance))->assertOk();
        $this->get(route('lots.index'))->assertOk();
    }
}
