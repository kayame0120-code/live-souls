<?php

namespace Tests\Feature;

use App\Models\E2eKey;
use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2eEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private IdolGroup $idolGroup;
    private GroupMember $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => Hash::make('secret-login-pw')]);
        $this->actingAs($this->user);

        $this->idolGroup = IdolGroup::create(['name' => 'テストグループ']);
        $this->member = GroupMember::create([
            'idol_group_id' => $this->idolGroup->id,
            'name' => 'テストメンバー',
            'color_name' => '赤', 'color_hex' => '#E53935',
            'source_type' => '公式', 'sort_order' => 1,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'person_name' => 'E2Eテスト名義',
            'group_id' => $this->idolGroup->id,
            'group_member_id' => $this->member->id,
        ], $overrides);
    }

    public function test_e2eプレフィックス付きの値はそのままDBに保存される(): void
    {
        $cipher = 'e2e:AAAA-fake-client-ciphertext-BBBB';

        $this->post(route('identities.store'), $this->payload([
            'member_no' => $cipher,
            'login_id' => $cipher,
            'fc_password' => $cipher,
        ]))->assertRedirect();

        $raw = DB::table('fc_memberships')->first();
        // クライアント暗号文は改変なしで保存される（サーバーは復号も再暗号化もしない）
        $this->assertSame($cipher, $raw->member_no);
        $this->assertSame($cipher, $raw->login_id);
        $this->assertSame($cipher, $raw->password);
    }

    public function test_e2e値はアクセサでもそのまま返る_サーバーで復号されない(): void
    {
        $cipher = 'e2e:client-only-ciphertext';

        $this->post(route('identities.store'), $this->payload(['member_no' => $cipher]));

        $membership = FcMembership::first();
        $this->assertSame($cipher, $membership->member_no);
    }

    public function test_平文送信はバリデーションで拒否されDBに一切保存されない(): void
    {
        $response = $this->post(route('identities.store'), $this->payload([
            'member_no' => 'TESTPLAINTEXT12345',
            'login_id' => 'plain-login-id',
            'fc_password' => 'plain-password',
        ]));

        $response->assertSessionHasErrors(['member_no', 'login_id', 'fc_password']);
        $this->assertDatabaseCount('fc_memberships', 0);
    }

    public function test_平文送信時にセッションとログに平文が残らない(): void
    {
        $this->post(route('identities.store'), $this->payload([
            'member_no' => 'TESTPLAINTEXT12345',
            'login_id' => 'TESTPLAINLOGINID',
            'fc_password' => 'TESTPLAINPASSWORD',
        ]));

        $logContent = file_exists(storage_path('logs/laravel.log'))
            ? file_get_contents(storage_path('logs/laravel.log'))
            : '';
        $this->assertStringNotContainsString('TESTPLAINTEXT12345', $logContent);
        $this->assertStringNotContainsString('TESTPLAINLOGINID', $logContent);
        $this->assertStringNotContainsString('TESTPLAINPASSWORD', $logContent);

        $sessionFiles = glob(storage_path('framework/sessions/*'));
        $sessionContent = implode('', array_map('file_get_contents', $sessionFiles ?: []));
        $this->assertStringNotContainsString('TESTPLAINTEXT12345', $sessionContent);
        $this->assertStringNotContainsString('TESTPLAINLOGINID', $sessionContent);
        $this->assertStringNotContainsString('TESTPLAINPASSWORD', $sessionContent);
    }

    public function test_レガシーAPP_KEY暗号文はアクセサで復号できる(): void
    {
        // v2.1以前のデータ形式（encryptedキャストで保存されたもの）の後方互換
        $person = \App\Models\Person::create(['user_id' => $this->user->id, 'name' => 'レガシー']);
        DB::table('fc_memberships')->insert([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $this->idolGroup->id,
            'artist_name' => 'テスト',
            'login_id' => Crypt::encryptString('legacy-login-id'),
            'password' => Crypt::encryptString('legacy-password'),
            'member_no' => '00187964', // 旧形式は平文
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $membership = FcMembership::first();
        $this->assertSame('legacy-login-id', $membership->login_id);
        $this->assertSame('legacy-password', $membership->password);
        $this->assertSame('00187964', $membership->member_no);
    }

    public function test_migrateルートでレガシー行がe2e化される(): void
    {
        $person = \App\Models\Person::create(['user_id' => $this->user->id, 'name' => 'レガシー移行']);
        DB::table('fc_memberships')->insert([
            'user_id' => $this->user->id,
            'person_id' => $person->id,
            'group_id' => $this->idolGroup->id,
            'artist_name' => 'テスト',
            'member_no' => 'plain-no',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $membership = FcMembership::first();

        // member_noのみがレガシー(login_id/passwordはnull=移行対象外)
        $cipher = 'e2e:migrated-ciphertext';
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.migrate', $membership->id), [
                'member_no' => $cipher,
            ])->assertOk();

        $raw = DB::table('fc_memberships')->where('id', $membership->id)->first();
        $this->assertSame($cipher, $raw->member_no);
    }

    public function test_パスワード検証エンドポイント(): void
    {
        $this->postJson(route('api.e2e.verify-password'), ['password' => 'secret-login-pw'])
            ->assertOk()->assertJson(['valid' => true]);

        $this->postJson(route('api.e2e.verify-password'), ['password' => 'wrong-pw'])
            ->assertOk()->assertJson(['valid' => false]);
    }

    public function test_鍵の保存と取得(): void
    {
        $this->postJson(route('api.e2e.keys.store'), [
            'wrapped_master_key_pw' => 'wrapped-pw-blob',
            'pw_salt' => 'salt-a',
            'wrapped_master_key_rk' => 'wrapped-rk-blob',
            'rk_salt' => 'salt-b',
        ])->assertOk();

        $this->getJson(route('api.e2e.keys'))
            ->assertOk()
            ->assertJson([
                'has_keys' => true,
                'wrapped_master_key_pw' => 'wrapped-pw-blob',
                'wrapped_master_key_rk' => 'wrapped-rk-blob',
            ]);
    }

    public function test_鍵の二重登録は409(): void
    {
        E2eKey::create([
            'user_id' => $this->user->id,
            'wrapped_master_key_pw' => 'a', 'pw_salt' => 'b',
            'wrapped_master_key_rk' => 'c', 'rk_salt' => 'd',
        ]);

        $this->postJson(route('api.e2e.keys.store'), [
            'wrapped_master_key_pw' => 'x', 'pw_salt' => 'x',
            'wrapped_master_key_rk' => 'x', 'rk_salt' => 'x',
        ])->assertStatus(409);
    }

    public function test_rewrapでパスワード側のみ更新される(): void
    {
        E2eKey::create([
            'user_id' => $this->user->id,
            'wrapped_master_key_pw' => 'old-pw-blob', 'pw_salt' => 'old-salt',
            'wrapped_master_key_rk' => 'rk-blob', 'rk_salt' => 'rk-salt',
        ]);

        $this->putJson(route('api.e2e.keys.rewrap'), [
            'wrapped_master_key_pw' => 'new-pw-blob',
            'pw_salt' => 'new-salt',
        ])->assertOk();

        $key = E2eKey::where('user_id', $this->user->id)->first();
        $this->assertSame('new-pw-blob', $key->wrapped_master_key_pw);
        $this->assertSame('rk-blob', $key->wrapped_master_key_rk); // リカバリー側は不変
    }

    public function test_他ユーザーの暗号文は取得できない403(): void
    {
        $this->post(route('identities.store'), $this->payload(['member_no' => 'e2e:mine']));
        $membership = FcMembership::first();

        $other = User::factory()->create();
        $this->actingAs($other)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('api.e2e.ciphertext', $membership->id))
            ->assertNotFound(); // UserScopeによりルートバインディングで404
    }

    public function test_パスワード未確認で名義詳細を開くと確認画面へ誘導され確認画面は正常表示される(): void
    {
        $this->post(route('identities.store'), $this->payload());
        $membership = FcMembership::first();

        // 未確認 → password.confirm へリダイレクト
        $this->get(route('identities.show', $membership))
            ->assertRedirect(route('password.confirm'));

        // 確認画面自体が500にならず表示できる（ConfirmPasswordViewResponse登録の回帰防止）
        $this->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('パスワード確認');

        // パスワード送信で確認が通り、名義詳細に到達できる
        $this->post(route('password.confirm'), ['password' => 'secret-login-pw'])
            ->assertRedirect();
        $this->get(route('identities.show', $membership))->assertOk();
    }

    public function test_本人は暗号文を取得できアクセスログが残る(): void
    {
        $this->post(route('identities.store'), $this->payload(['member_no' => 'e2e:mine']));
        $membership = FcMembership::first();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('api.e2e.ciphertext', $membership->id))
            ->assertOk()
            ->assertJson(['member_no' => 'e2e:mine']);

        $this->assertDatabaseHas('e2e_access_logs', [
            'user_id' => $this->user->id,
            'fc_membership_id' => $membership->id,
            'action' => 'get_ciphertext',
        ]);
    }
}
