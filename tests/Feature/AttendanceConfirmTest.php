<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesDomainData;
use Tests\TestCase;

/**
 * 公演日経過→「参戦した？」確認（spec §5・T8・§9テスト）。
 * 自動遷移はしない。確定操作でのみ attended / skipped に遷移する。
 */
class AttendanceConfirmTest extends TestCase
{
    use RefreshDatabase;
    use MakesDomainData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_公演日経過のplannedはホームで確認表示され自動遷移しない(): void
    {
        $past = $this->makeEvent('過ぎた予定', now()->subDays(3)->format('Y-m-d'));
        $attendance = $this->makeAttendance($this->user, $past, 'planned');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('参戦した？')
            ->assertSee('過ぎた予定');

        // ホームを見ただけでは status は planned のまま（自動遷移しない）
        $this->assertSame('planned', $attendance->fresh()->status);
    }

    public function test_参戦したの確定でattendedに遷移する(): void
    {
        $past = $this->makeEvent('過ぎた予定', now()->subDays(3)->format('Y-m-d'));
        $attendance = $this->makeAttendance($this->user, $past, 'planned');

        $this->patch(route('attendances.confirm', $attendance), ['decision' => 'attended'])
            ->assertRedirect(route('home'));

        $this->assertSame('attended', $attendance->fresh()->status);
    }

    public function test_行かなかったの確定でskippedに遷移する(): void
    {
        $past = $this->makeEvent('過ぎた予定', now()->subDays(3)->format('Y-m-d'));
        $attendance = $this->makeAttendance($this->user, $past, 'planned');

        $this->patch(route('attendances.confirm', $attendance), ['decision' => 'skipped'])
            ->assertRedirect(route('home'));

        $this->assertSame('skipped', $attendance->fresh()->status);
    }

    public function test_未来のplannedは確認対象にならない(): void
    {
        $future = $this->makeEvent('未来の予定', now()->addDays(5)->format('Y-m-d'));
        $this->makeAttendance($this->user, $future, 'planned');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('参戦した？');
    }

    public function test_他ユーザーの参戦は確認確定できない404(): void
    {
        $other = User::factory()->create();
        $past = $this->makeEvent('他人の予定', now()->subDay()->format('Y-m-d'));
        $attendance = $this->makeAttendance($other, $past, 'planned');

        $this->patch(route('attendances.confirm', $attendance), ['decision' => 'attended'])
            ->assertStatus(404);
    }
}
