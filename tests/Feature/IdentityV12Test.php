<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\IdentityGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * v1.2 名義まわり（指示書T3-T4・T9・§9テスト）:
 * email の encrypted 保存・伏字表示 / oshi_color プリセット検証 / 当選率の非表示。
 */
class IdentityV12Test extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;
    private IdentityGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->group = IdentityGroup::create(['user_id' => $this->user->id, 'name' => 'FC']);
    }

    private function storePayload(array $override = []): array
    {
        return array_merge([
            'person_name' => '太郎',
            'group_id' => $this->group->id,
            'artist_name' => 'A',
        ], $override);
    }

    public function test_emailはencryptedで保存され平文はDBに出ない(): void
    {
        $this->post(route('identities.store'), $this->storePayload([
            'email' => 'fc-login@example.com',
        ]))->assertRedirect();

        $membership = FcMembership::first();
        // アクセサ経由では復号され、DBの生値は暗号化されている
        $this->assertSame('fc-login@example.com', $membership->email);
        $raw = \DB::table('fc_memberships')->where('id', $membership->id)->value('email');
        $this->assertNotSame('fc-login@example.com', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_名義詳細でemailは伏字表示されコピー用にdata属性へ復号値が入る(): void
    {
        $this->post(route('identities.store'), $this->storePayload([
            'email' => 'secret@example.com',
        ]));
        $membership = FcMembership::first();

        $response = $this->get(route('identities.show', $membership));
        $response->assertOk();
        // 画面本文に平文を出さない（伏字）。コピー用の data-copy 属性にのみ復号値
        $response->assertSee('data-copy="secret@example.com"', false);
        $response->assertDontSee('>secret@example.com<', false);
    }

    public function test_oshi_colorはプリセット11色のみ許可される(): void
    {
        // プリセット内（赤）はOK
        $this->post(route('identities.store'), $this->storePayload([
            'oshi_color' => '#E60033',
        ]))->assertRedirect();
        $this->assertSame('#E60033', FcMembership::first()->oshi_color);

        // プリセット外は拒否
        $this->post(route('identities.store'), $this->storePayload([
            'person_name' => '次郎',
            'oshi_color' => '#123456',
        ]))->assertSessionHasErrors('oshi_color');
    }

    public function test_名義詳細に当選率は表示されず当落一覧が出る(): void
    {
        $membership = $this->makeMembership($this->user);
        $event = $this->makeEvent('当選公演', '2026-05-01');
        $attendance = $this->makeAttendance($this->user, $event, 'planned');
        $attendance->fcMemberships()->attach($membership->id, ['result' => 'won']);

        $response = $this->get(route('identities.show', $membership));
        $response->assertOk()
            // v1.3: 見出しは mockup 準拠「この名義の申込・当落」。当落一覧が出て当選率は出ない
            ->assertSee('申込・当落')
            ->assertSee('当選')
            ->assertDontSee('当選率');
    }

    public function test_winRateメソッドは撤去されている(): void
    {
        $this->assertFalse(method_exists(FcMembership::class, 'winRate'));
        $this->assertFalse(method_exists(FcMembership::class, 'winCount'));
        $this->assertFalse(method_exists(FcMembership::class, 'applicationCount'));
    }
}
