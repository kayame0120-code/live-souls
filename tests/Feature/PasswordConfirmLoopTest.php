<?php

namespace Tests\Feature;

use App\Models\FcMembership;
use App\Models\IdolGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordConfirmLoopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => bcrypt('secret-login-pw')]);

        $group = IdolGroup::firstOrCreate(['name' => 'テスト']);
        $personId = DB::table('persons')->insertGetId([
            'user_id' => $this->user->id,
            'name' => Crypt::encryptString('テスト太郎'),
            'phone' => Crypt::encryptString('090-1111-2222'),
            'address' => Crypt::encryptString('東京都千代田区'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $personId,
            'group_id' => $group->id,
            'artist_name' => 'テスト',
            'member_no' => 'e2e:migrated',
        ]);
    }

    public function test_personsレガシー状態でconfirmed_password_statusが200JSONを返す(): void
    {
        $this->actingAs($this->user)
            ->getJson('/user/confirmed-password-status')
            ->assertOk()
            ->assertJsonStructure(['confirmed']);
    }

    public function test_パスワード確認後にe2e_migrateへ戻る(): void
    {
        $this->actingAs($this->user)
            ->withSession(['url.intended' => route('e2e.migrate-page')])
            ->post('/user/confirm-password', ['password' => 'secret-login-pw'])
            ->assertRedirect(route('e2e.migrate-page'));
    }
}
