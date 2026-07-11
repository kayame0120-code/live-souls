<?php

namespace Tests\Unit;

use App\Models\FcMembership;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 更新期間の自動計算（spec §5-6・§7 テスト化必須）。
 * 有効期限 = joined_on の月の1日 + 1年 − 1日（毎年同月日）
 * 更新受付 = 有効期限月の前月2日 〜 有効期限日（境界日を含む）
 */
class RenewalWindowTest extends TestCase
{
    private function membership(?string $joinedOn): FcMembership
    {
        $m = new FcMembership();
        $m->joined_on = $joinedOn;
        return $m;
    }

    public function test_spec例_2022年10月入会は有効期限9月30日_受付8月2日から(): void
    {
        $m = $this->membership('2022-10-01');
        $today = Carbon::parse('2026-07-08');

        $this->assertSame('2026-09-30', $m->expiryDate($today)->format('Y-m-d'));
        $this->assertSame('2026-08-02', $m->renewalWindowStart($today)->format('Y-m-d'));
    }

    public function test_1月入会は有効期限12月31日_受付11月2日から同年内(): void
    {
        $m = $this->membership('2024-01-01');
        $today = Carbon::parse('2026-11-15');

        $this->assertSame('2026-12-31', $m->expiryDate($today)->format('Y-m-d'));
        $this->assertSame('2026-11-02', $m->renewalWindowStart($today)->format('Y-m-d'));
        // 受付期間内（11/2〜12/31）
        $this->assertTrue($m->isInRenewalWindow($today));
    }

    public function test_2月入会は有効期限1月31日_受付12月2日から年跨ぎ(): void
    {
        $m = $this->membership('2024-02-01');

        // 12月中: 次の有効期限は翌年1/31、受付開始は今年12/2 → 年跨ぎで受付中
        $today = Carbon::parse('2026-12-15');
        $this->assertSame('2027-01-31', $m->expiryDate($today)->format('Y-m-d'));
        $this->assertSame('2026-12-02', $m->renewalWindowStart($today)->format('Y-m-d'));
        $this->assertTrue($m->isInRenewalWindow($today));

        // 年が明けて1月中も受付中
        $today = Carbon::parse('2027-01-20');
        $this->assertSame('2027-01-31', $m->expiryDate($today)->format('Y-m-d'));
        $this->assertTrue($m->isInRenewalWindow($today));
    }

    public function test_受付境界日_初日2日と期限日当日はどちらも受付中(): void
    {
        // 2022-10入会 → 有効期限9/30・受付8/2〜9/30
        $m = $this->membership('2022-10-01');

        // 受付初日（8/2）
        $this->assertTrue($m->isInRenewalWindow(Carbon::parse('2026-08-02')));
        // 受付前日（8/1）は対象外
        $this->assertFalse($m->isInRenewalWindow(Carbon::parse('2026-08-01')));
        // 期限日当日（9/30）
        $this->assertTrue($m->isInRenewalWindow(Carbon::parse('2026-09-30')));
        // 期限翌日（10/1）は対象外
        $this->assertFalse($m->isInRenewalWindow(Carbon::parse('2026-10-01')));
    }

    public function test_3月入会はうるう年で2月29日が期限(): void
    {
        $m = $this->membership('2023-03-01');

        // 平年
        $this->assertSame('2026-02-28', $m->expiryDate(Carbon::parse('2026-02-01'))->format('Y-m-d'));
        // うるう年（2028年）
        $this->assertSame('2028-02-29', $m->expiryDate(Carbon::parse('2028-02-01'))->format('Y-m-d'));
    }

    public function test_有効期限を過ぎたら翌年の期限に切り替わる(): void
    {
        $m = $this->membership('2022-10-01');

        // 10/1（期限9/30の翌日）→ 翌年の9/30
        $this->assertSame('2027-09-30', $m->expiryDate(Carbon::parse('2026-10-01'))->format('Y-m-d'));
    }

    public function test_joined_onがnullなら全てnullで受付中でない(): void
    {
        $m = $this->membership(null);

        $this->assertNull($m->expiryDate());
        $this->assertNull($m->renewalWindowStart());
        $this->assertFalse($m->isInRenewalWindow());
    }
}
