<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Models\IdolGroup;
use Database\Seeders\StartoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StartoSeeder::class);
    }

    public function test_STARTO全グループが投入される(): void
    {
        $this->assertSame(14, IdolGroup::count());
    }

    public function test_Snow_Man_9人(): void
    {
        $group = IdolGroup::where('name', 'Snow Man')->first();
        $this->assertNotNull($group);
        $this->assertNull($group->status);
        $this->assertSame(9, $group->members()->count());
    }

    public function test_NEWSは単色_spec_v2_4訂正(): void
    {
        $news = IdolGroup::where('name', 'NEWS')->first();
        $members = $news->members->pluck('color_name', 'name');

        $this->assertSame('紫', $members['小山慶一郎']);
        $this->assertSame('緑', $members['加藤シゲアキ']);
        $this->assertSame('黄', $members['増田貴久']);
    }

    public function test_全メンバーにcolor_hexが設定される(): void
    {
        $this->assertSame(0, GroupMember::whereNull('color_hex')->count());
    }

    public function test_嵐はステータス休止(): void
    {
        $arashi = IdolGroup::where('name', '嵐')->first();
        $this->assertSame('休止', $arashi->status);
    }

    public function test_KAT_TUNはステータス解散(): void
    {
        $kattun = IdolGroup::where('name', 'KAT-TUN')->first();
        $this->assertSame('解散', $kattun->status);
    }

    public function test_冪等に再実行できる(): void
    {
        $this->seed(StartoSeeder::class);
        $this->assertSame(14, IdolGroup::count());
        $this->assertSame(82, GroupMember::count());
    }
}
