<?php

namespace Tests\Unit;

use App\Support\JoinedMonthConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * joined_month→joined_on 変換の突合検証（指示書 §1-1-3）。
 */
class JoinedMonthConverterTest extends TestCase
{
    public function test_YYYY_MM形式は1日固定のdateに変換される(): void
    {
        $this->assertSame('2022-10-01', JoinedMonthConverter::toDate('2022-10'));
        $this->assertSame('2019-03-01', JoinedMonthConverter::toDate('2019-03'));
        $this->assertSame('2026-12-01', JoinedMonthConverter::toDate('2026-12'));
    }

    public function test_形式不一致は例外で中断される(): void
    {
        $invalid = ['2022/10', '2022-1', '10-2022', '2022年10月', 'abc', '', '2022-10-01'];

        foreach ($invalid as $value) {
            try {
                JoinedMonthConverter::toDate($value);
                $this->fail("\"{$value}\" が例外にならなかった");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_月が範囲外の場合も例外(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JoinedMonthConverter::toDate('2022-13');
    }
}
