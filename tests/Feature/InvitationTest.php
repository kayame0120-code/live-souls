<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_有効な招待コードで登録できる(): void
    {
        $issuer = User::factory()->create();
        Invitation::create([
            'code' => 'valid-code-123',
            'issued_by' => $issuer->id,
        ]);

        $this->get(route('register.show', 'valid-code-123'))
            ->assertOk();

        $this->post(route('register.store', 'valid-code-123'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'invited_by' => $issuer->id,
        ]);

        $invitation = Invitation::where('code', 'valid-code-123')->first();
        $this->assertNotNull($invitation->used_by);
        $this->assertNotNull($invitation->used_at);
    }

    public function test_使用済み招待コードは拒否される(): void
    {
        $issuer = User::factory()->create();
        $usedBy = User::factory()->create();

        Invitation::create([
            'code' => 'used-code',
            'issued_by' => $issuer->id,
            'used_by' => $usedBy->id,
            'used_at' => now(),
        ]);

        $this->get(route('register.show', 'used-code'))
            ->assertStatus(403);
    }

    public function test_期限切れ招待コードは拒否される(): void
    {
        $issuer = User::factory()->create();

        Invitation::create([
            'code' => 'expired-code',
            'issued_by' => $issuer->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('register.show', 'expired-code'))
            ->assertStatus(403);
    }

    public function test_存在しない招待コードは拒否される(): void
    {
        $this->get(route('register.show', 'nonexistent'))
            ->assertStatus(403);
    }

    public function test_同一招待コードは二度使えない(): void
    {
        $issuer = User::factory()->create();
        Invitation::create([
            'code' => 'race-code',
            'issued_by' => $issuer->id,
        ]);

        $this->post(route('register.store', 'race-code'), [
            'name' => 'ユーザーA',
            'email' => 'a@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'a@example.com']);

        $this->post(route('logout'));

        $this->get(route('register.show', 'race-code'))
            ->assertStatus(403);
    }

    public function test_招待コード発行と失効(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invitations.store'))
            ->assertRedirect(route('invitations.index'));

        $invitation = Invitation::where('issued_by', $user->id)->first();
        $this->assertNotNull($invitation);

        $this->actingAs($user)
            ->delete(route('invitations.destroy', $invitation))
            ->assertRedirect(route('invitations.index'));

        $invitation->refresh();
        $this->assertNotNull($invitation->expires_at);
    }
}
