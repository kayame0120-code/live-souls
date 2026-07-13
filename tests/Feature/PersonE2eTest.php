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

class PersonE2eTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => bcrypt('secret-login-pw')]);
        $this->actingAs($this->user);

        $personId = DB::table('persons')->insertGetId([
            'user_id' => $this->user->id,
            'name' => Crypt::encryptString('テスト太郎'),
            'phone' => Crypt::encryptString('090-1111-2222'),
            'address' => Crypt::encryptString('東京都千代田区1-1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->person = Person::withoutGlobalScopes()->find($personId);
    }

    public function test_C_B1_E2E移行後のDB生値はCrypt復号で例外になる(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.person-migrate', $this->person), [
                'phone' => 'e2e:encrypted-phone',
                'address' => 'e2e:encrypted-address',
            ])->assertOk();

        $raw = DB::table('persons')->where('id', $this->person->id)->first();
        $this->assertStringStartsWith('e2e:', $raw->phone);

        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        Crypt::decryptString($raw->phone);
    }

    public function test_C_B2_レガシーencrypted値が移行で失われない(): void
    {
        $originalPhone = Person::withoutGlobalScopes()->find($this->person->id)->phone;
        $originalAddress = Person::withoutGlobalScopes()->find($this->person->id)->address;
        $this->assertSame('090-1111-2222', $originalPhone);
        $this->assertSame('東京都千代田区1-1', $originalAddress);

        $ciphertext = $this->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('api.e2e.person-ciphertext', $this->person))
            ->assertOk()
            ->json();

        $this->assertSame('090-1111-2222', $ciphertext['phone']);
        $this->assertSame('東京都千代田区1-1', $ciphertext['address']);
    }

    public function test_C_B3_冪等性_e2e化済みの行に再移行しても値が変わらない(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.person-migrate', $this->person), [
                'phone' => 'e2e:first-encrypt',
                'address' => 'e2e:first-encrypt-addr',
            ])->assertOk();

        $raw1 = DB::table('persons')->where('id', $this->person->id)->first();

        $this->person->refresh();
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.person-migrate', $this->person), [
                'phone' => 'e2e:first-encrypt',
                'address' => 'e2e:first-encrypt-addr',
            ])->assertOk();

        $raw2 = DB::table('persons')->where('id', $this->person->id)->first();
        $this->assertSame($raw1->phone, $raw2->phone);
        $this->assertSame($raw1->address, $raw2->address);
    }

    public function test_C_B4_トランザクション内で部分更新が残らない(): void
    {
        $originalRaw = DB::table('persons')->where('id', $this->person->id)->first();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.person-migrate', $this->person), [
                'phone' => 'e2e:only-phone',
            ])->assertStatus(422);

        $afterRaw = DB::table('persons')->where('id', $this->person->id)->first();
        $this->assertSame($originalRaw->phone, $afterRaw->phone);
        $this->assertSame($originalRaw->address, $afterRaw->address);
    }

    public function test_C_B5_RequireE2eMigrationがpersonsレガシー行を検出する(): void
    {
        $group = IdolGroup::firstOrCreate(['name' => 'テスト']);
        FcMembership::create([
            'user_id' => $this->user->id,
            'person_id' => $this->person->id,
            'group_id' => $group->id,
            'artist_name' => 'テスト',
        ]);

        $this->get(route('identities.index'))->assertRedirect(route('e2e.migrate-page'));

        DB::table('persons')->where('id', $this->person->id)->update([
            'phone' => 'e2e:migrated-phone',
            'address' => 'e2e:migrated-address',
        ]);

        $this->get(route('identities.index'))->assertOk();
    }

    public function test_C_B6_平文拒否_e2e先頭でないphoneは422(): void
    {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.e2e.person-migrate', $this->person), [
                'phone' => '090-plain-text',
                'address' => 'e2e:valid',
            ])->assertUnprocessable();
    }

    public function test_C_B7_nameとbirth_dateに差分がない(): void
    {
        $person = Person::withoutGlobalScopes()->find($this->person->id);
        $casts = $person->getCasts();
        $this->assertSame('encrypted', $casts['name']);
        $this->assertSame('encrypted', $casts['birth_date']);
        $this->assertArrayNotHasKey('phone', $casts);
        $this->assertArrayNotHasKey('address', $casts);
    }
}
