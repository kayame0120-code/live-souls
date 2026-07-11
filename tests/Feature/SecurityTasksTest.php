<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\GroupMember;
use App\Models\IdolGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * セキュリティ残タスク3件のテスト:
 * ①既存データの一括E2E化（migration-status / migrate API）
 * ②2FA設定画面（settings.security）
 * ③レガシー行のE2E化により表示DOMから平文が消える（①の帰結）
 */
class SecurityTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => Hash::make('secret-login-pw')]);
        $this->actingAs($this->user);
    }

    private function makeLegacyMembership(User $user, string $memberNo = '00187964'): FcMembership
    {
        $group = IdolGroup::firstOrCreate(['name' => 'テストグループ']);
        $person = Person::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'レガシー名義']);

        $id = DB::table('fc_memberships')->insertGetId([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'group_id' => $group->id,
            'artist_name' => 'テスト',
            'member_no' => $memberNo, // 旧形式: 平文
            'login_id' => Crypt::encryptString('legacy-login'), // 旧形式: APP_KEY暗号化
            'password' => Crypt::encryptString('legacy-pass'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return FcMembership::withoutGlobalScopes()->find($id);
    }

    // ---- ①一括E2E化 ----

    public function test_旧形式の名義がmigration_statusで検出される(): void
    {
        $this->makeLegacyMembership($this->user);

        $this->getJson(route('api.e2e.migration-status'))
            ->assertOk()
            ->assertJsonCount(1, 'pending');
    }

    public function test_e2e済みの名義はmigration_statusに出ない(): void
    {
        $group = IdolGroup::firstOrCreate(['name' => 'テストグループ']);
        $person = Person::withoutGlobalScopes()->create(['user_id' => $this->user->id, 'name' => 'E2E名義']);
        DB::table('fc_memberships')->insert([
            'user_id' => $this->user->id, 'person_id' => $person->id, 'group_id' => $group->id,
            'artist_name' => 'テスト',
            'member_no' => 'e2e:cipher-a', 'login_id' => 'e2e:cipher-b', 'password' => 'e2e:cipher-c',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson(route('api.e2e.migration-status'))
            ->assertOk()
            ->assertJsonCount(0, 'pending');
    }

    public function test_migrateはe2e暗号文を受け付けDBを更新する(): void
    {
        $membership = $this->makeLegacyMembership($this->user);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.migrate', $membership->id), [
                'member_no' => 'e2e:new-cipher-1',
                'login_id' => 'e2e:new-cipher-2',
                'password' => 'e2e:new-cipher-3',
            ])->assertOk();

        $raw = DB::table('fc_memberships')->where('id', $membership->id)->first();
        $this->assertSame('e2e:new-cipher-1', $raw->member_no);
        $this->assertSame('e2e:new-cipher-2', $raw->login_id);
        $this->assertSame('e2e:new-cipher-3', $raw->password);

        // 移行のアクセスログが残る
        $this->assertDatabaseHas('e2e_access_logs', [
            'fc_membership_id' => $membership->id,
            'action' => 'migrate_to_e2e',
        ]);
    }

    public function test_migrateは平文を拒否する(): void
    {
        $membership = $this->makeLegacyMembership($this->user);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.migrate', $membership->id), [
                'member_no' => 'plain-value',
            ])->assertUnprocessable();

        // DBは変更されない（平文のまま = 元の値）
        $raw = DB::table('fc_memberships')->where('id', $membership->id)->first();
        $this->assertSame('00187964', $raw->member_no);
    }

    public function test_migrateは他ユーザーの名義を更新できない(): void
    {
        $other = User::factory()->create();
        $membership = $this->makeLegacyMembership($other);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.migrate', $membership->id), [
                'member_no' => 'e2e:attack',
            ])->assertNotFound(); // UserScopeでルートバインディング404
    }

    public function test_migration完了後は名義詳細DOMに平文が乗らない(): void
    {
        $membership = $this->makeLegacyMembership($this->user);

        // E2E化前: レガシー平文がコピー属性に乗る（従来挙動）
        $before = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('identities.show', $membership));
        $before->assertSee('data-copy="00187964"', false);

        // E2E化
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.migrate', $membership->id), [
                'member_no' => 'e2e:cipher-x',
                'login_id' => 'e2e:cipher-y',
                'password' => 'e2e:cipher-z',
            ])->assertOk();

        // E2E化後: DOMには暗号文のみ・平文は出ない
        $after = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('identities.show', $membership));
        $after->assertDontSee('00187964', false);
        $after->assertDontSee('legacy-login', false);
        $after->assertSee('data-copy="e2e:cipher-x"', false);
    }

    // ---- ②2FA設定画面 ----

    public function test_セキュリティ設定画面が表示される_未設定状態(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('settings.security'))
            ->assertOk()
            ->assertSee('2段階認証')
            ->assertSee('2段階認証を有効にする');
    }

    public function test_セキュリティ設定はパスワード確認必須(): void
    {
        $this->get(route('settings.security'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_2FA有効化フローが通る(): void
    {
        // 有効化（QR発行）
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(url('user/two-factor-authentication'))
            ->assertRedirect();

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);

        // pending状態の画面にQRコードが出る
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('settings.security'))
            ->assertOk()
            ->assertSee('QRコード')
            ->assertSee('有効化を完了する');
    }

    public function test_2FA確認済みなら画面にリカバリーコードが出る(): void
    {
        // 有効化→確認済み状態を直接作る
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(url('user/two-factor-authentication'));
        $this->user->refresh();
        $this->user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('settings.security'))
            ->assertOk()
            ->assertSee('2段階認証は有効です')
            ->assertSee('リカバリーコード');
    }

    public function test_ホームにセキュリティ設定への導線がある(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('セキュリティ設定');
    }
}
