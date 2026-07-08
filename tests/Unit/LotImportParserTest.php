<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Venue;
use App\Services\LotImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 貼り付け一括インポートのパーサ（spec §5-10・§7「無言で捨てない」）。
 */
class LotImportParserTest extends TestCase
{
    use RefreshDatabase;

    private LotImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LotImportService();
    }

    public function test_年あり日付と既存会場と公演名が抽出される(): void
    {
        $user = User::factory()->create();
        Venue::create(['name' => '横浜アリーナ', 'created_by' => $user->id]);

        $rows = $this->service->parse("2026/09/12 横浜アリーナ Prism of Night");

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-12', $rows[0]['event_date']);
        $this->assertSame('横浜アリーナ', $rows[0]['venue_name']);
        $this->assertSame('Prism of Night', $rows[0]['event_name']);
    }

    public function test_日本語日付形式も解析できる(): void
    {
        $rows = $this->service->parse("2026年9月20日 追加公演");

        $this->assertSame('2026-09-20', $rows[0]['event_date']);
        $this->assertSame('追加公演', $rows[0]['event_name']);
    }

    public function test_年なし日付は今年と解釈される(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08'));

        $rows = $this->service->parse("9/20 大阪公演");
        $this->assertSame('2026-09-20', $rows[0]['event_date']);

        $rows = $this->service->parse("9月20日 大阪公演");
        $this->assertSame('2026-09-20', $rows[0]['event_date']);

        Carbon::setTestNow();
    }

    public function test_解析不能行も空欄のまま捨てずに返す(): void
    {
        $rows = $this->service->parse("よくわからないメモ書き");

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['event_date']);
        $this->assertNull($rows[0]['venue_name']);
        $this->assertSame('よくわからないメモ書き', $rows[0]['event_name']);
    }

    public function test_不正な日付は日付として扱わない(): void
    {
        $rows = $this->service->parse("2026/13/45 存在しない日付の公演");

        $this->assertNull($rows[0]['event_date']);
    }

    public function test_空行はスキップされる(): void
    {
        $rows = $this->service->parse("\n\n2026/09/12 公演A\n\n\n2026/09/13 公演B\n");

        $this->assertCount(2, $rows);
    }
}
