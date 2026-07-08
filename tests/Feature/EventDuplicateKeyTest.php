<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ★v1.3：重複判定キー＝venue_id × event_date × start_time（cc_instructions v1.3 §7 T2/T3・spec §5）。
 * 昼夜（start_time違い）は別公演として通し、重複扱いにしない。
 * v1.4: events は tour 配下（create の第1引数は tour_id・第2引数は event_label）。
 */
class EventDuplicateKeyTest extends TestCase
{
    use RefreshDatabase;

    private EventService $service;
    private int $venueId;
    private int $tourId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventService();
        $user = User::factory()->create();
        $this->venueId = Venue::create(['name' => '大阪城ホール', 'created_by' => $user->id])->id;
        $this->tourId = Tour::create(['name' => 'Xツアー'])->id;
    }

    public function test_T2_同日昼夜は別レコードとして登録される(): void
    {
        $this->service->create($this->tourId, null, '2026-07-29', '13:30', $this->venueId);
        $this->service->create($this->tourId, null, '2026-07-29', '18:00', $this->venueId);

        // 会場×日が同じでも start_time が違えば別 event（2件）
        $this->assertSame(2, Event::where('venue_id', $this->venueId)
            ->whereDate('event_date', '2026-07-29')->count());
    }

    public function test_T3_開演違いは重複扱いにならない(): void
    {
        $this->service->create($this->tourId, null, '2026-07-29', '13:30', $this->venueId);

        // 同一開演 → 重複として拾う
        $this->assertCount(1, $this->service->findDuplicates($this->venueId, '2026-07-29', '13:30'));
        // 異なる開演（昼夜）→ 重複ではない
        $this->assertCount(0, $this->service->findDuplicates($this->venueId, '2026-07-29', '18:00'));
    }

    public function test_T3_開演NULL同士のみ同一公演の重複として警告する(): void
    {
        $this->service->create($this->tourId, null, '2026-07-29', null, $this->venueId);

        // 両方 NULL で会場×日一致 → 重複警告対象
        $this->assertCount(1, $this->service->findDuplicates($this->venueId, '2026-07-29', null));
        // 既存が NULL のとき、時刻ありは別公演（重複にしない）
        $this->assertCount(0, $this->service->findDuplicates($this->venueId, '2026-07-29', '13:30'));
    }
}
